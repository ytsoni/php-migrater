<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\PhpVersion;
use Ylab\PhpMigrater\Fixer\Fixers\CurlyBraceAccessFixer;

/**
 * Detects curly brace array/string access: $arr{0}
 * Deprecated in PHP 7.4, removed in PHP 8.0.
 */
final class CurlyBraceVisitor extends NodeVisitorAbstract
{
    /** @var Issue[] */
    private array $issues = [];
    private string $filePath = '';
    private string $sourceCode = '';

    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
        $this->issues = [];
    }

    public function setSourceCode(string $sourceCode): void
    {
        $this->sourceCode = $sourceCode;
    }

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function enterNode(Node $node): ?int
    {
        if (!$node instanceof Node\Expr\ArrayDimFetch) {
            return null;
        }

        // php-parser normalizes both $a[0] and $a{0} to ArrayDimFetch.
        // We need to check the original source to distinguish them.
        if ($this->sourceCode !== '' && $node->dim !== null) {
            $startPos = $node->dim->getStartFilePos();
            if ($startPos > 0 && ($this->sourceCode[$startPos - 1] ?? '') === '{') {
                $this->issues[] = new Issue(
                    file: $this->filePath,
                    line: $node->getStartLine(),
                    column: 0,
                    severity: Severity::Error,
                    message: 'Curly brace array/string access ($var{0}) is deprecated in PHP 7.4 and removed in PHP 8.0.',
                    category: IssueCategory::CurlyBraceAccess,
                    affectedFrom: PhpVersion::PHP_74,
                    affectedTo: PhpVersion::PHP_80,
                    suggestedFixerClass: CurlyBraceAccessFixer::class,
                );
            }
        }

        return null;
    }
}
