<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Config;

use Symfony\Component\Finder\Finder;

final class Configuration
{
    /** @var string[] */
    private array $paths;

    /** @var string[] */
    private array $excludePaths;

    /** @var class-string[] */
    private array $plugins;

    /**
     * @param list<string> $paths
     * @param list<string> $excludePaths
     * @param list<class-string> $plugins
     */
    public function __construct(
        private readonly PhpVersion $sourceVersion,
        private readonly PhpVersion $targetVersion,
        array $paths = [],
        array $excludePaths = [],
        private readonly string $reportOutputDir = 'report',
        private readonly string $testOutputDir = 'tests/migration',
        private readonly int $parallelWorkers = 1,
        array $plugins = [],
        private readonly int $webGuiPort = 8080,
        private readonly string $stateFile = '.php-migrater-state.json',
    ) {
        if ($sourceVersion->isNewerThanOrEqual($targetVersion)) {
            throw new \InvalidArgumentException(
                "Source version ({$sourceVersion->value}) must be older than target version ({$targetVersion->value})"
            );
        }

        $this->paths = $paths;
        $this->excludePaths = $excludePaths;
        $this->plugins = $plugins;
    }

    private string $configPath = '';

    public static function fromFile(string $configPath): self
    {
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Configuration file not found: {$configPath}");
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException("Configuration file must return an array: {$configPath}");
        }

        $instance = new self(
            sourceVersion: PhpVersion::fromString($config['source'] ?? throw new \RuntimeException('Missing "source" in config')),
            targetVersion: PhpVersion::fromString($config['target'] ?? throw new \RuntimeException('Missing "target" in config')),
            paths: (array) ($config['paths'] ?? []),
            excludePaths: (array) ($config['exclude'] ?? []),
            reportOutputDir: $config['report_output'] ?? 'report',
            testOutputDir: $config['test_output'] ?? 'tests/migration',
            parallelWorkers: (int) ($config['parallel'] ?? 1),
            plugins: (array) ($config['plugins'] ?? []),
            webGuiPort: (int) ($config['web_port'] ?? 8080),
            stateFile: $config['state_file'] ?? '.php-migrater-state.json',
        );
        $instance->configPath = realpath($configPath) ?: $configPath;
        return $instance;
    }

    /** Alias for fromFile() used by CLI commands. */
    public static function load(string $configPath): self
    {
        return self::fromFile($configPath);
    }

    /** @param list<string> $paths */
    public static function fromDefaults(PhpVersion $source, PhpVersion $target, array $paths = []): self
    {
        return new self(
            sourceVersion: $source,
            targetVersion: $target,
            paths: $paths,
        );
    }

    public function getSourceVersion(): PhpVersion
    {
        return $this->sourceVersion;
    }

    public function getTargetVersion(): PhpVersion
    {
        return $this->targetVersion;
    }

    /** @return PhpVersion[] */
    public function getVersionRange(): array
    {
        return PhpVersion::range($this->sourceVersion, $this->targetVersion);
    }

    /** @return string[] */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /** @return string[] */
    public function getExcludes(): array
    {
        return $this->excludePaths;
    }

    /** @return string[] */
    public function getExcludePaths(): array
    {
        return $this->excludePaths;
    }

    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    public function getReportOutputDir(): string
    {
        return $this->reportOutputDir;
    }

    public function getTestOutputDir(): string
    {
        return $this->testOutputDir;
    }

    public function getParallelWorkers(): int
    {
        return $this->parallelWorkers;
    }

    /** @return class-string[] */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function getWebGuiPort(): int
    {
        return $this->webGuiPort;
    }

    public function getStateFile(): string
    {
        return $this->stateFile;
    }

    /** @param list<string>|null $paths */
    public function createFinder(?array $paths = null): Finder
    {
        $finder = Finder::create()
            ->files()
            ->name('*.php')
            ->sortByName();

        $searchPaths = $paths ?? $this->paths;
        if (!empty($searchPaths)) {
            $finder->in($searchPaths);
        }

        foreach ($this->excludePaths as $exclude) {
            $finder->exclude($exclude);
        }

        return $finder;
    }
}
