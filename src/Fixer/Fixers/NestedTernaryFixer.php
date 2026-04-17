<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer\Fixers;

use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Fixer\FixerInterface;

/**
 * Adds parentheses to nested ternary expressions.
 * Nested ternaries without explicit parentheses are deprecated in PHP 7.4
 * and throw a compile error in PHP 8.0.
 */
final class NestedTernaryFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'nested_ternary';
    }

    public function getDescription(): string
    {
        return 'Add parentheses to nested ternary expressions';
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function supports(Issue $issue): bool
    {
        return $issue->category === IssueCategory::NestedTernary;
    }

    public function fix(string $sourceCode, array $issues): string
    {
        if (empty($issues)) {
            return $sourceCode;
        }

        // Use regex to find and wrap the inner ternary in parentheses
        // Pattern: expr ? expr : expr ? expr : expr
        // This is a simplified heuristic — complex nesting may need AST-based fixing

        // Match ternary ? : that is itself one branch of another ternary
        $sourceCode = preg_replace_callback(
            '/(\?[^?:]+:)\s*([^;,\)]+\?[^;,\)]+:[^;,\)]+)/',
            function (array $matches): string {
                return $matches[1] . ' (' . trim($matches[2]) . ')';
            },
            $sourceCode,
        );

        return $sourceCode;
    }
}
