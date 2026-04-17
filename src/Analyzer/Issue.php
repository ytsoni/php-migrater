<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use Ylab\PhpMigrater\Config\PhpVersion;

final readonly class Issue
{
    public function __construct(
        public string $file,
        public int $line,
        public int $column,
        public Severity $severity,
        public string $message,
        public IssueCategory $category,
        public ?PhpVersion $affectedFrom = null,
        public ?PhpVersion $affectedTo = null,
        public ?string $suggestedFixerClass = null,
    ) {}

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
