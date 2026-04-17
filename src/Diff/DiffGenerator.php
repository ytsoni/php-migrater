<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Diff;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Generates unified diffs from before/after code strings.
 */
final class DiffGenerator
{
    private readonly Differ $differ;

    public function __construct()
    {
        $this->differ = new Differ(new UnifiedDiffOutputBuilder("--- original\n+++ modified\n", true));
    }

    public function generate(string $original, string $modified, string $fileName = ''): DiffResult
    {
        $diff = $this->differ->diff($original, $modified);
        $hasChanges = $original !== $modified;

        return new DiffResult(
            fileName: $fileName,
            original: $original,
            modified: $modified,
            unifiedDiff: $diff,
            hasChanges: $hasChanges,
        );
    }
}

final readonly class DiffResult
{
    public function __construct(
        public string $fileName,
        public string $original,
        public string $modified,
        public string $unifiedDiff,
        public bool $hasChanges,
    ) {}

    public function getAddedLineCount(): int
    {
        return preg_match_all('/^\+[^+]/m', $this->unifiedDiff);
    }

    public function getRemovedLineCount(): int
    {
        return preg_match_all('/^-[^-]/m', $this->unifiedDiff);
    }

    public function toArray(): array
    {
        return [
            'file' => $this->fileName,
            'has_changes' => $this->hasChanges,
            'added_lines' => $this->getAddedLineCount(),
            'removed_lines' => $this->getRemovedLineCount(),
            'diff' => $this->unifiedDiff,
        ];
    }
}
