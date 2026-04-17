<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

final readonly class GeneratedTest
{
    public function __construct(
        public string $testClassName,
        public string $testCode,
        public string $targetFilePath,
        public string $generatorName,
    ) {}
}
