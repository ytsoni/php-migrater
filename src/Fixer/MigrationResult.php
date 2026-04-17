<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer;

final class MigrationResult
{
    public function __construct(
        public readonly int $applied,
        public readonly int $skipped,
        public readonly int $failed,
        public readonly bool $aborted,
    ) {}

    public function totalProcessed(): int
    {
        return $this->applied + $this->skipped + $this->failed;
    }

    public function isSuccessful(): bool
    {
        return $this->failed === 0 && !$this->aborted;
    }
}
