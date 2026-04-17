<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use Ylab\PhpMigrater\Config\PhpVersion;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function weight(): int
    {
        return match ($this) {
            self::Error => 10,
            self::Warning => 3,
            self::Info => 1,
        };
    }
}

enum IssueCategory: string
{
    case LooseComparison = 'loose_comparison';
    case ResourceToObject = 'resource_to_object';
    case CurlyBraceAccess = 'curly_brace_access';
    case DynamicProperty = 'dynamic_property';
    case ImplicitNullable = 'implicit_nullable';
    case StringToNumber = 'string_to_number';
    case NestedTernary = 'nested_ternary';
    case DeprecatedFunction = 'deprecated_function';
    case RemovedFunction = 'removed_function';
    case TypeSystem = 'type_system';
    case Syntax = 'syntax';
    case Compatibility = 'compatibility';
    case Dependency = 'dependency';
    case Other = 'other';
}

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
