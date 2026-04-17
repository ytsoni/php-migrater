<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\WebGui;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Ylab\PhpMigrater\Config\Configuration;

/**
 * Starts the web GUI using PHP's built-in server.
 */
final class Server
{
    public function __construct(
        private readonly Configuration $config,
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8484,
    ) {}

    /**
     * Start the built-in web server (blocking).
     */
    public function start(OutputInterface $output): void
    {
        $routerFile = $this->createRouter();
        $docRoot = $this->getDocRoot();

        $process = new Process([
            PHP_BINARY, '-S',
            "{$this->host}:{$this->port}",
            '-t', $docRoot,
            $routerFile,
        ]);

        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $process->run(function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });
    }

    private function getDocRoot(): string
    {
        return dirname(__DIR__, 2) . '/resources/web';
    }

    private function createRouter(): string
    {
        $configFile = $this->config->getConfigPath();
        $docRoot = $this->getDocRoot();
        $escapedConfig = addslashes($configFile);
        $escapedDocRoot = addslashes($docRoot);

        $router = <<<'PHP'
<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API routes
if (str_starts_with($uri, '/api/')) {
    $autoloadPaths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../../autoload.php',
    ];
    foreach ($autoloadPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }

    $config = \Ylab\PhpMigrater\Config\Configuration::load('CONFIG_PLACEHOLDER');
    $controller = new \Ylab\PhpMigrater\WebGui\ApiController($config);
    echo $controller->handle($_SERVER['REQUEST_METHOD'], $uri);
    return true;
}

// Static files
$filePath = 'DOCROOT_PLACEHOLDER' . $uri;
if ($uri === '/' || $uri === '') {
    $filePath = 'DOCROOT_PLACEHOLDER/index.html';
}

if (file_exists($filePath)) {
    return false; // Let PHP built-in server handle it
}

// Fallback to index.html for SPA
$filePath = 'DOCROOT_PLACEHOLDER/index.html';
if (file_exists($filePath)) {
    readfile($filePath);
    return true;
}

http_response_code(404);
echo 'Not Found';
return true;
PHP;

        $router = str_replace('CONFIG_PLACEHOLDER', $escapedConfig, $router);
        $router = str_replace('DOCROOT_PLACEHOLDER', $escapedDocRoot, $router);

        $tmpRouter = sys_get_temp_dir() . '/php-migrater-router-' . md5($configFile) . '.php';
        file_put_contents($tmpRouter, $router);

        return $tmpRouter;
    }
}
