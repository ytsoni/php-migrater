<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

interface TestGeneratorInterface
{
    public function getName(): string;

    /**
     * Generate test code for a given source file.
     *
     * @return array<GeneratedTest>
     */
    public function generate(string $sourceCode, string $filePath): array;
}
