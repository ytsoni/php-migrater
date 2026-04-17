<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

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
