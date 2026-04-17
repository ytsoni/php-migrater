<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer;

use Symfony\Component\Process\Process;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Config\PhpVersion;

/**
 * Adapter that invokes Rector for automated PHP upgrades.
 * Gracefully degrades if Rector is not installed.
 */
final class RectorAdapter
{
    private ?string $rectorPath = null;

    /** @var array<string, string> PhpVersion value => Rector set constant */
    private const VERSION_SETS = [
        '5.3' => 'UpgradeSetList::PHP_53',
        '5.4' => 'UpgradeSetList::PHP_54',
        '5.5' => 'UpgradeSetList::PHP_55',
        '5.6' => 'UpgradeSetList::PHP_56',
        '7.0' => 'UpgradeSetList::PHP_70',
        '7.1' => 'UpgradeSetList::PHP_71',
        '7.2' => 'UpgradeSetList::PHP_72',
        '7.3' => 'UpgradeSetList::PHP_73',
        '7.4' => 'UpgradeSetList::PHP_74',
        '8.0' => 'UpgradeSetList::PHP_80',
        '8.1' => 'UpgradeSetList::PHP_81',
        '8.2' => 'UpgradeSetList::PHP_82',
        '8.3' => 'UpgradeSetList::PHP_83',
        '8.4' => 'UpgradeSetList::PHP_84',
    ];

    public function isAvailable(): bool
    {
        return $this->findRector() !== null;
    }

    /**
     * Run Rector in dry-run mode to preview changes.
     *
     * @return string Rector's diff output
     */
    public function dryRun(string $path, Configuration $config): string
    {
        $rector = $this->findRector();
        if ($rector === null) {
            return '';
        }

        $configFile = $this->generateRectorConfig($config);

        try {
            $process = new Process([
                $rector, 'process',
                $path,
                '--config', $configFile,
                '--dry-run',
                '--no-ansi',
            ]);

            $process->setTimeout(300);
            $process->run();

            return $process->getOutput();
        } finally {
            if (file_exists($configFile)) {
                unlink($configFile);
            }
        }
    }

    /**
     * Run Rector to apply fixes.
     *
     * @return string Rector's output
     */
    public function apply(string $path, Configuration $config): string
    {
        $rector = $this->findRector();
        if ($rector === null) {
            return '';
        }

        $configFile = $this->generateRectorConfig($config);

        try {
            $process = new Process([
                $rector, 'process',
                $path,
                '--config', $configFile,
                '--no-ansi',
            ]);

            $process->setTimeout(300);
            $process->run();

            return $process->getOutput();
        } finally {
            if (file_exists($configFile)) {
                unlink($configFile);
            }
        }
    }

    private function findRector(): ?string
    {
        if ($this->rectorPath !== null) {
            return $this->rectorPath !== '' ? $this->rectorPath : null;
        }

        $candidates = [
            'vendor/bin/rector',
            'rector',
        ];

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, '--version']);
            $process->setTimeout(5);
            $process->run();

            if ($process->isSuccessful()) {
                $this->rectorPath = $candidate;
                return $candidate;
            }
        }

        $this->rectorPath = '';
        return null;
    }

    private function generateRectorConfig(Configuration $config): string
    {
        $sets = [];
        $versions = PhpVersion::range($config->getSourceVersion(), $config->getTargetVersion());

        // Skip the source version itself, apply sets from source+1 to target
        foreach ($versions as $version) {
            if ($version === $config->getSourceVersion()) {
                continue;
            }
            if (isset(self::VERSION_SETS[$version->value])) {
                $sets[] = self::VERSION_SETS[$version->value];
            }
        }

        $setsCode = implode(",\n            ", $sets);
        $pathsCode = '';
        foreach ($config->getPaths() as $path) {
            $escapedPath = addslashes($path);
            $pathsCode .= "        \$rectorConfig->paths([__DIR__ . '/{$escapedPath}']);\n";
        }

        $phpCode = <<<PHP
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\UpgradeSetList;

return RectorConfig::configure()
    ->withSets([
            {$setsCode}
    ]);
PHP;

        $tmpFile = sys_get_temp_dir() . '/php-migrater-rector-' . uniqid() . '.php';
        file_put_contents($tmpFile, $phpCode);

        return $tmpFile;
    }
}
