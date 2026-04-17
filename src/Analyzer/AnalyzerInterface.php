<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use SplFileInfo;
use Ylab\PhpMigrater\Config\Configuration;

interface AnalyzerInterface
{
    public function getName(): string;

    /** @return Issue[] */
    public function analyze(SplFileInfo $file, Configuration $config): array;
}
