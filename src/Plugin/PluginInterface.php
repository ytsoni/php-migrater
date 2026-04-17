<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Plugin;

use Ylab\PhpMigrater\Analyzer\AnalyzerInterface;
use Ylab\PhpMigrater\Fixer\FixerInterface;
use Ylab\PhpMigrater\Reporter\ReporterInterface;
use Ylab\PhpMigrater\TestGenerator\TestGeneratorInterface;

interface PluginInterface
{
    public function getName(): string;

    /** @return AnalyzerInterface[] */
    public function getAnalyzers(): array;

    /** @return FixerInterface[] */
    public function getFixers(): array;

    /** @return TestGeneratorInterface[] */
    public function getTestGenerators(): array;

    /** @return ReporterInterface[] */
    public function getReporters(): array;
}
