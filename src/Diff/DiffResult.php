<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Diff;

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
