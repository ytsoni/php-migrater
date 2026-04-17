<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use Ylab\PhpMigrater\Config\PhpVersion;

final class Issue
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly int $column,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly IssueCategory $category,
        public readonly ?PhpVersion $affectedFrom = null,
        public readonly ?PhpVersion $affectedTo = null,
        public readonly ?string $suggestedFixerClass = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'category' => $this->category->value,
            'affected_from' => $this->affectedFrom?->value,
            'affected_to' => $this->affectedTo?->value,
            'suggested_fixer' => $this->suggestedFixerClass,
        ];
    }
}
