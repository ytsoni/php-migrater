<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Parallel;

use Symfony\Component\Finder\SplFileInfo;

/**
 * Splits a file list into balanced chunks for parallel processing.
 */
final class FileChunker
{
    /**
     * @param array<SplFileInfo> $files
     * @return array<array<SplFileInfo>> Chunks of files
     */
    public function chunk(array $files, int $workerCount): array
    {
        if ($workerCount <= 1 || empty($files)) {
            return [$files];
        }

        // Sort by file size descending for better load balancing
        usort($files, function (SplFileInfo $a, SplFileInfo $b): int {
            return $b->getSize() <=> $a->getSize();
        });

        // Round-robin distribute to chunks (largest files first for balance)
        $chunks = array_fill(0, $workerCount, []);
        $chunkSizes = array_fill(0, $workerCount, 0);

        foreach ($files as $file) {
            // Assign to the chunk with least total size
            $minIdx = array_search(min($chunkSizes), $chunkSizes, true);
            $chunks[$minIdx][] = $file;
            $chunkSizes[$minIdx] += $file->getSize();
        }

        // Remove empty chunks
        return array_values(array_filter($chunks, fn($c) => !empty($c)));
    }
}
