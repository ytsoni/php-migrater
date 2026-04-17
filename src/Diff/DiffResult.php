<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Diff;

final class DiffResult
{
    public function __construct(
        public readonly string $fileName,
        public readonly string $original,
        public readonly string $modified,
        public readonly string $unifiedDiff,
        public readonly bool $hasChanges,
    ) {}

    public function getAddedLineCount(): int
    {
        return (int) preg_match_all('/^\+[^+]/m', $this->unifiedDiff);
    }

    public function getRemovedLineCount(): int
    {
        return (int) preg_match_all('/^-[^-]/m', $this->unifiedDiff);
    }

    /** @return array<string, mixed> */
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
