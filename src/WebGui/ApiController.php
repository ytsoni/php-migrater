<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\WebGui;

use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Plugin\PluginRegistry;
use Ylab\PhpMigrater\Reporter\JsonReporter;
use Ylab\PhpMigrater\Reporter\MigrationReport;

/**
 * Handles API requests for the web GUI.
 */
final class ApiController
{
    private readonly PluginRegistry $registry;

    public function __construct(
        private readonly Configuration $config,
    ) {
        $this->registry = new PluginRegistry($this->config);
    }

    public function handle(string $method, string $path): string
    {
        return match (true) {
            $method === 'GET' && $path === '/api/status' => $this->status(),
            $method === 'GET' && $path === '/api/analyze' => $this->analyze(),
            $method === 'GET' && $path === '/api/config' => $this->config(),
            default => $this->jsonResponse(['error' => 'Not found'], 404),
        };
    }

    private function status(): string
    {
        return $this->jsonResponse([
            'status' => 'running',
            'source' => $this->config->getSourceVersion()->value,
            'target' => $this->config->getTargetVersion()->value,
            'paths' => $this->config->getPaths(),
        ]);
    }

    private function analyze(): string
    {
        $analyzers = $this->registry->getAnalyzers();
        $finder = $this->config->createFinder();

        $report = new MigrationReport($this->config->getSourceVersion(), $this->config->getTargetVersion());
        $filesAnalyzed = 0;

        foreach ($finder as $file) {
            $filesAnalyzed++;
            $allIssues = [];

            foreach ($analyzers as $analyzer) {
                $allIssues = array_merge($allIssues, $analyzer->analyze($file, $this->config));
            }

            if (!empty($allIssues)) {
                $report->addFileIssues($file->getRealPath() ?: $file->getPathname(), $allIssues);
            }
        }

        $report->setFilesAnalyzed($filesAnalyzed);
        $report->finish();

        $reporter = new JsonReporter();
        return $reporter->render($report);
    }

    private function config(): string
    {
        return $this->jsonResponse([
            'sourceVersion' => $this->config->getSourceVersion()->value,
            'targetVersion' => $this->config->getTargetVersion()->value,
            'paths' => $this->config->getPaths(),
            'excludes' => $this->config->getExcludes(),
            'parallel' => $this->config->getParallelWorkers(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function jsonResponse(array $data, int $statusCode = 200): string
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
