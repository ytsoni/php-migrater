<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Parallel;

use Symfony\Component\Process\Process;
use Ylab\PhpMigrater\Analyzer\AnalyzerInterface;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Config\Configuration;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Parallel worker pool for analysis using symfony/process.
 * Spawns child processes that analyze file chunks independently.
 */
final class WorkerPool
{
    private readonly FileChunker $chunker;

    public function __construct(
        private readonly int $maxWorkers = 4,
    ) {
        $this->chunker = new FileChunker();
    }

    /**
     * Analyze files in parallel using child processes.
     *
     * @param array<SplFileInfo> $files
     * @param array<AnalyzerInterface> $analyzers
     * @return array<string, array<Issue>> filePath => issues
     */
    public function analyze(
        array $files,
        array $analyzers,
        Configuration $config,
        ?callable $onFileComplete = null,
    ): array {
        $chunks = $this->chunker->chunk($files, $this->maxWorkers);

        if (count($chunks) <= 1) {
            return $this->analyzeSequential($files, $analyzers, $config, $onFileComplete);
        }

        return $this->analyzeParallel($chunks, $analyzers, $config, $onFileComplete);
    }

    /**
     * @param array<SplFileInfo> $files
     * @param array<AnalyzerInterface> $analyzers
     * @return array<string, array<Issue>>
     */
    private function analyzeSequential(
        array $files,
        array $analyzers,
        Configuration $config,
        ?callable $onFileComplete,
    ): array {
        $results = [];

        foreach ($files as $file) {
            $filePath = $file->getRealPath() ?: $file->getPathname();
            $issues = [];

            foreach ($analyzers as $analyzer) {
                $issues = array_merge($issues, $analyzer->analyze($file, $config));
            }

            if (!empty($issues)) {
                $results[$filePath] = $issues;
            }

            if ($onFileComplete !== null) {
                $onFileComplete($filePath, $issues);
            }
        }

        return $results;
    }

    /**
     * @param array<array<SplFileInfo>> $chunks
     * @param array<AnalyzerInterface> $analyzers
     * @return array<string, array<Issue>>
     */
    private function analyzeParallel(
        array $chunks,
        array $analyzers,
        Configuration $config,
        ?callable $onFileComplete,
    ): array {
        // Write chunk manifests to temp files
        $tempFiles = [];
        $processes = [];

        foreach ($chunks as $i => $chunk) {
            $manifest = [];
            foreach ($chunk as $file) {
                $manifest[] = $file->getRealPath() ?: $file->getPathname();
            }

            $tempFile = sys_get_temp_dir() . '/php-migrater-chunk-' . $i . '-' . uniqid() . '.json';
            file_put_contents($tempFile, json_encode($manifest));
            $tempFiles[] = $tempFile;

            // Spawn a child process that analyzes the chunk
            $workerScript = $this->getWorkerScript();
            $process = new Process([
                PHP_BINARY, '-r', $workerScript,
                $tempFile,
                $config->getSourceVersion()->value,
                $config->getTargetVersion()->value,
            ]);

            $process->setTimeout(600);
            $process->start();
            $processes[] = $process;
        }

        // Wait for all processes
        $allResults = [];
        foreach ($processes as $i => $process) {
            $process->wait();

            $outputFile = $tempFiles[$i] . '.results';
            if (file_exists($outputFile)) {
                $content = file_get_contents($outputFile);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $filePath => $issueData) {
                            $issues = array_map(
                                fn($data) => new Issue(
                                    file: $data['file'],
                                    line: $data['line'],
                                    column: $data['column'] ?? 0,
                                    severity: \Ylab\PhpMigrater\Analyzer\Severity::from($data['severity']),
                                    message: $data['message'],
                                    category: \Ylab\PhpMigrater\Analyzer\IssueCategory::from($data['category']),
                                    suggestedFixerClass: $data['suggested_fixer'] ?? null,
                                ),
                                $issueData,
                            );
                            $allResults[$filePath] = $issues;

                            if ($onFileComplete !== null) {
                                $onFileComplete($filePath, $issues);
                            }
                        }
                    }
                }
                @unlink($outputFile);
            }

            @unlink($tempFiles[$i]);
        }

        return $allResults;
    }

    private function getWorkerScript(): string
    {
        return <<<'PHP'
<?php
// Worker script for parallel analysis
// Arguments: $argv[1] = manifest file, $argv[2] = source version, $argv[3] = target version

$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
];

foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;
        break;
    }
}

$manifest = json_decode(file_get_contents($argv[1]), true);
$sourceVersion = \Ylab\PhpMigrater\Config\PhpVersion::fromString($argv[2]);
$targetVersion = \Ylab\PhpMigrater\Config\PhpVersion::fromString($argv[3]);

$detector = new \Ylab\PhpMigrater\Analyzer\AstIssueDetector();
$config = new class($sourceVersion, $targetVersion) {
    public function __construct(
        private $source,
        private $target,
    ) {}
    public function getSourceVersion() { return $this->source; }
    public function getTargetVersion() { return $this->target; }
};

$results = [];
foreach ($manifest as $filePath) {
    $file = new \SplFileInfo($filePath);
    $issues = $detector->analyze($file, $config);
    if (!empty($issues)) {
        $results[$filePath] = array_map(fn($i) => $i->toArray(), $issues);
    }
}

file_put_contents($argv[1] . '.results', json_encode($results));
PHP;
    }
}
