<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer\Fixers;

use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Fixer\FixerInterface;

/**
 * Replaces curly brace array/string access $arr{0} with $arr[0].
 * Deprecated in PHP 7.4, removed in PHP 8.0.
 */
final class CurlyBraceAccessFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'curly_brace_access';
    }

    public function getDescription(): string
    {
        return 'Replace $var{index} with $var[index]';
    }

    public function getPriority(): int
    {
        return 80; // High priority — simple mechanical fix
    }

    public function supports(Issue $issue): bool
    {
        return $issue->category === IssueCategory::CurlyBraceAccess;
    }

    public function fix(string $sourceCode, array $issues): string
    {
        if (empty($issues)) {
            return $sourceCode;
        }

        // Replace $var{expr} with $var[expr]
        // Handles: $str{0}, $arr{$key}, $obj->prop{$i}
        return preg_replace(
            '/(\$[\w>-]+)\{([^}]+)\}/',
            '$1[$2]',
            $sourceCode,
        );
    }
}
