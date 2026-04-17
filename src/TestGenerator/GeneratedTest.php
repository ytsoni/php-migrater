<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

final class GeneratedTest
{
    public function __construct(
        public readonly string $testClassName,
        public readonly string $testCode,
        public readonly string $targetFilePath,
        public readonly string $generatorName,
    ) {}
}
