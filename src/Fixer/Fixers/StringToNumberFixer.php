<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer\Fixers;

use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Fixer\FixerInterface;

/**
 * Wraps ambiguous string-to-number comparisons with explicit type casts.
 * In PHP 8.0, non-numeric strings compared to ints are no longer cast to 0.
 */
final class StringToNumberFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'string_to_number';
    }

    public function getDescription(): string
    {
        return 'Add explicit casts for ambiguous string-to-number comparisons';
    }

    public function getPriority(): int
    {
        return 45;
    }

    public function supports(Issue $issue): bool
    {
        return $issue->category === IssueCategory::StringToNumber;
    }

    public function fix(string $sourceCode, array $issues): string
    {
        // This fixer adds comments flagging the comparison rather than auto-fixing,
        // since the correct fix depends on the developer's intent.
        if (empty($issues)) {
            return $sourceCode;
        }

        $lines = explode("\n", $sourceCode);
        $affectedLines = array_map(fn(Issue $i) => $i->line, $issues);

        foreach ($affectedLines as $lineNum) {
            $idx = $lineNum - 1;
            if (isset($lines[$idx])) {
                $line = $lines[$idx];
                // Only add comment if not already flagged
                if (!str_contains($line, '/* PHP 8.0: comparison behavior changed */')) {
                    $lines[$idx] = rtrim($line) . ' /* PHP 8.0: comparison behavior changed */';
                }
            }
        }

        return implode("\n", $lines);
    }
}
