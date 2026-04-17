<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Plugin;

use Ylab\PhpMigrater\Analyzer\AnalyzerInterface;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Fixer\FixerInterface;
use Ylab\PhpMigrater\Fixer\FixerRegistry;
use Ylab\PhpMigrater\Reporter\ReporterInterface;
use Ylab\PhpMigrater\TestGenerator\TestGeneratorInterface;

final class PluginRegistry
{
    /** @var PluginInterface[] */
    private array $plugins = [];

    /** @var AnalyzerInterface[] */
    private array $analyzers = [];

    /** @var FixerInterface[] */
    private array $fixers = [];

    /** @var TestGeneratorInterface[] */
    private array $testGenerators = [];

    /** @var ReporterInterface[] */
    private array $reporters = [];

    private bool $resolved = false;

    public function __construct(?Configuration $config = null)
    {
        // Always register the core plugin
        $this->register(new CorePlugin());

        // Discover plugins from config
        if ($config !== null) {
            $this->discoverFromConfig($config);
        }
    }

    public function register(PluginInterface $plugin): void
    {
        $this->plugins[$plugin->getName()] = $plugin;
        $this->resolved = false;
    }

    public function discoverFromComposer(string $projectRoot): void
    {
        $installedPath = $projectRoot . '/vendor/composer/installed.json';
        if (!file_exists($installedPath)) {
            return;
        }

        $content = file_get_contents($installedPath);
        if ($content === false) {
            return;
        }
        $installed = json_decode($content, true);
        $packages = $installed['packages'] ?? $installed;

        foreach ($packages as $package) {
            $pluginClasses = $package['extra']['php-migrater']['plugins'] ?? [];
            foreach ($pluginClasses as $className) {
                if (class_exists($className) && is_subclass_of($className, PluginInterface::class)) {
                    $this->register(new $className());
                }
            }
        }
    }

    public function discoverFromConfig(Configuration $config): void
    {
        foreach ($config->getPlugins() as $className) {
            if (class_exists($className) && is_subclass_of($className, PluginInterface::class)) {
                $this->register(new $className());
            }
        }
    }

    /** @return AnalyzerInterface[] */
    public function getAnalyzers(): array
    {
        $this->resolve();
        return $this->analyzers;
    }

    /** @return FixerInterface[] */
    public function getFixers(): array
    {
        $this->resolve();
        return $this->fixers;
    }

    /** @return TestGeneratorInterface[] */
    public function getTestGenerators(): array
    {
        $this->resolve();
        return $this->testGenerators;
    }

    /** @return ReporterInterface[] */
    public function getReporters(): array
    {
        $this->resolve();
        return $this->reporters;
    }

    /** @return PluginInterface[] */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function getFixerRegistry(): FixerRegistry
    {
        $this->resolve();
        $registry = new FixerRegistry();
        foreach ($this->fixers as $fixer) {
            $registry->register($fixer);
        }
        return $registry;
    }

    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->analyzers = [];
        $this->fixers = [];
        $this->testGenerators = [];
        $this->reporters = [];

        foreach ($this->plugins as $plugin) {
            foreach ($plugin->getAnalyzers() as $analyzer) {
                $this->analyzers[] = $analyzer;
            }
            foreach ($plugin->getFixers() as $fixer) {
                $this->fixers[] = $fixer;
            }
            foreach ($plugin->getTestGenerators() as $generator) {
                $this->testGenerators[] = $generator;
            }
            foreach ($plugin->getReporters() as $reporter) {
                $this->reporters[] = $reporter;
            }
        }

        usort($this->fixers, fn(FixerInterface $a, FixerInterface $b) => $b->getPriority() - $a->getPriority());
        $this->resolved = true;
    }
}
