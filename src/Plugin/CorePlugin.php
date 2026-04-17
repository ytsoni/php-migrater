<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Plugin;

use Ylab\PhpMigrater\Analyzer\AnalyzerInterface;
use Ylab\PhpMigrater\Analyzer\AstIssueDetector;
use Ylab\PhpMigrater\Analyzer\CompatibilityScanner;
use Ylab\PhpMigrater\Analyzer\DependencyAnalyzer;
use Ylab\PhpMigrater\Analyzer\VersionDetector;
use Ylab\PhpMigrater\Fixer\FixerInterface;
use Ylab\PhpMigrater\Fixer\Fixers\CurlyBraceAccessFixer;
use Ylab\PhpMigrater\Fixer\Fixers\DynamicPropertyFixer;
use Ylab\PhpMigrater\Fixer\Fixers\ImplicitNullableFixer;
use Ylab\PhpMigrater\Fixer\Fixers\LooseComparisonFixer;
use Ylab\PhpMigrater\Fixer\Fixers\NestedTernaryFixer;
use Ylab\PhpMigrater\Fixer\Fixers\ResourceToObjectFixer;
use Ylab\PhpMigrater\Fixer\Fixers\StringToNumberFixer;
use Ylab\PhpMigrater\Reporter\ConsoleReporter;
use Ylab\PhpMigrater\Reporter\HtmlReporter;
use Ylab\PhpMigrater\Reporter\JsonReporter;
use Ylab\PhpMigrater\Reporter\ReporterInterface;
use Ylab\PhpMigrater\TestGenerator\BehavioralTestGenerator;
use Ylab\PhpMigrater\TestGenerator\CharacterizationTestGenerator;
use Ylab\PhpMigrater\TestGenerator\TestGeneratorInterface;

/**
 * Built-in core plugin that provides all default analyzers, fixers, test generators, and reporters.
 */
final class CorePlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'core';
    }

    public function getAnalyzers(): array
    {
        return [
            new AstIssueDetector(),
            new VersionDetector(),
            new CompatibilityScanner(),
            new DependencyAnalyzer(),
        ];
    }

    public function getFixers(): array
    {
        return [
            new CurlyBraceAccessFixer(),
            new NestedTernaryFixer(),
            new ImplicitNullableFixer(),
            new LooseComparisonFixer(),
            new StringToNumberFixer(),
            new ResourceToObjectFixer(),
            new DynamicPropertyFixer(),
        ];
    }

    public function getTestGenerators(): array
    {
        return [
            new CharacterizationTestGenerator(),
            new BehavioralTestGenerator(),
        ];
    }

    public function getReporters(): array
    {
        return [
            new ConsoleReporter(),
            new JsonReporter(),
            new HtmlReporter(),
        ];
    }
}
