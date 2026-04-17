<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use SplFileInfo;
use Symfony\Component\Process\Process;
use Ylab\PhpMigrater\Config\Configuration;

/**
 * Adapter for PHPCompatibility via PHP_CodeSniffer.
 * Gracefully degrades if phpcs/PHPCompatibility is not installed.
 */
final class CompatibilityScanner implements AnalyzerInterface
{
    private ?string $phpcsPath = null;

    public function getName(): string
    {
        return 'compatibility_scanner';
    }

    public function analyze(SplFileInfo $file, Configuration $config): array
    {
        $phpcs = $this->findPhpcs();
        if ($phpcs === null) {
            return [];
        }

        $targetVersion = $config->getTargetVersion()->majorMinor();
        $sourceVersion = $config->getSourceVersion()->majorMinor();
        $testVersion = "{$sourceVersion}-{$targetVersion}";

        $process = new Process([
            $phpcs,
            '--standard=PHPCompatibility',
            '--report=json',
            "--runtime-set", "testVersion", $testVersion,
            '--no-colors',
            $file->getPathname(),
        ]);

        $process->setTimeout(60);
        $process->run();

        $output = $process->getOutput();
        if (empty($output)) {
            return [];
        }

        return $this->parseJsonOutput($output, $file->getPathname());
    }

    public function isAvailable(): bool
    {
        return $this->findPhpcs() !== null;
    }

    private function findPhpcs(): ?string
    {
        if ($this->phpcsPath !== null) {
            return $this->phpcsPath !== '' ? $this->phpcsPath : null;
        }

        $candidates = [
            'vendor/bin/phpcs',
            'phpcs',
        ];

        foreach ($candidates as $candidate) {
            $process = new Process([$candidate, '--version']);
            $process->setTimeout(5);
            $process->run();

            if ($process->isSuccessful()) {
                $this->phpcsPath = $candidate;
                return $candidate;
            }
        }

        $this->phpcsPath = '';
        return null;
    }

    /** @return Issue[] */
    private function parseJsonOutput(string $json, string $fallbackFile): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $issues = [];
        $files = $data['files'] ?? [];

        foreach ($files as $filePath => $fileData) {
            $messages = $fileData['messages'] ?? [];
            foreach ($messages as $msg) {
                $severity = match ($msg['type'] ?? 'WARNING') {
                    'ERROR' => Severity::Error,
                    'WARNING' => Severity::Warning,
                    default => Severity::Info,
                };

                $issues[] = new Issue(
                    file: $filePath,
                    line: (int) ($msg['line'] ?? 0),
                    column: (int) ($msg['column'] ?? 0),
                    severity: $severity,
                    message: $msg['message'] ?? 'Unknown compatibility issue',
                    category: IssueCategory::Compatibility,
                );
            }
        }

        return $issues;
    }
}
