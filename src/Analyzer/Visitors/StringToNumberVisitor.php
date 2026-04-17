<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\PhpVersion;
use Ylab\PhpMigrater\Fixer\Fixers\StringToNumberFixer;

/**
 * Detects implicit string-to-number comparisons that change in PHP 8.0.
 *
 * In PHP 8.0, comparing a string to a number uses number comparison only if
 * the string is numeric. Previously non-numeric strings were cast to 0.
 */
final class StringToNumberVisitor extends NodeVisitorAbstract
{
    /** @var Issue[] */
    private array $issues = [];
    private string $filePath = '';

    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
        $this->issues = [];
    }

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function enterNode(Node $node): ?int
    {
        if (!$node instanceof Node\Expr\BinaryOp) {
            return null;
        }

        // Only check comparison operators
        if (!$node instanceof Node\Expr\BinaryOp\Smaller
            && !$node instanceof Node\Expr\BinaryOp\SmallerOrEqual
            && !$node instanceof Node\Expr\BinaryOp\Greater
            && !$node instanceof Node\Expr\BinaryOp\GreaterOrEqual
            && !$node instanceof Node\Expr\BinaryOp\Spaceship
        ) {
            return null;
        }

        $left = $node->left;
        $right = $node->right;

        if ($this->isMixedStringNumberComparison($left, $right)
            || $this->isMixedStringNumberComparison($right, $left)
        ) {
            $this->issues[] = new Issue(
                file: $this->filePath,
                line: $node->getStartLine(),
                column: 0,
                severity: Severity::Warning,
                message: 'Comparison between string and number changed behavior in PHP 8.0. Non-numeric strings are no longer cast to 0.',
                category: IssueCategory::StringToNumber,
                affectedFrom: PhpVersion::PHP_80,
                suggestedFixerClass: StringToNumberFixer::class,
            );
        }

        return null;
    }

    private function isMixedStringNumberComparison(Node\Expr $a, Node\Expr $b): bool
    {
        $isNumber = $a instanceof Node\Scalar\Int_ || $a instanceof Node\Scalar\Float_;
        $isString = $b instanceof Node\Scalar\String_ && !is_numeric($b->value);

        return $isNumber && $isString;
    }
}
