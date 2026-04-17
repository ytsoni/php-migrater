<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes generated tests to disk.
 */
final class TestWriter
{
    /**
     * @param array<GeneratedTest> $tests
     * @return array<string> List of written file paths
     */
    public function write(array $tests, ?OutputInterface $output = null): array
    {
        $written = [];

        foreach ($tests as $test) {
            $dir = dirname($test->targetFilePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (file_exists($test->targetFilePath)) {
                $output?->writeln("<comment>Skipped (exists): {$test->targetFilePath}</comment>");
                continue;
            }

            file_put_contents($test->targetFilePath, $test->testCode);
            $written[] = $test->targetFilePath;

            $output?->writeln("<info>Generated: {$test->targetFilePath}</info>");
        }

        return $written;
    }
}
