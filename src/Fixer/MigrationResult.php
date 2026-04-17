<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer;

final readonly class MigrationResult
{
    public function __construct(
        public int $applied,
        public int $skipped,
        public int $failed,
        public bool $aborted,
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
