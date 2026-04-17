<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\PhpVersion;
use Ylab\PhpMigrater\Fixer\Fixers\LooseComparisonFixer;

/**
 * Detects loose comparisons (== / !=) that change behavior in PHP 8.0.
 *
 * In PHP 8.0, comparison between string and number changed:
 * - 0 == "foo" was TRUE in PHP 7.x, FALSE in PHP 8.0
 * - "" == 0 was TRUE in PHP 7.x, FALSE in PHP 8.0
 */
final class LooseComparisonVisitor extends NodeVisitorAbstract
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
        if (!$node instanceof Node\Expr\BinaryOp\Equal
            && !$node instanceof Node\Expr\BinaryOp\NotEqual
        ) {
            return null;
        }

        $left = $node->left;
        $right = $node->right;

        if ($this->isRiskyComparison($left, $right) || $this->isRiskyComparison($right, $left)) {
            $operator = $node instanceof Node\Expr\BinaryOp\Equal ? '==' : '!=';
            $this->issues[] = new Issue(
                file: $this->filePath,
                line: $node->getStartLine(),
                column: 0,
                severity: Severity::Warning,
                message: "Loose comparison ({$operator}) may change behavior in PHP 8.0. Consider using strict comparison.",
                category: IssueCategory::LooseComparison,
                affectedFrom: PhpVersion::PHP_80,
                suggestedFixerClass: LooseComparisonFixer::class,
            );
        }

        return null;
    }

    private function isRiskyComparison(Node\Expr $a, Node\Expr $b): bool
    {
        // 0 == $string or "" == $var
        if ($a instanceof Node\Scalar\Int_ && $a->value === 0) {
            return true;
        }
        if ($a instanceof Node\Scalar\String_ && $a->value === '') {
            return true;
        }
        // false == $var
        if ($a instanceof Node\Expr\ConstFetch) {
            $name = strtolower($a->name->toString());
            if ($name === 'false' || $name === 'null') {
                return true;
            }
        }

        return false;
    }
}
