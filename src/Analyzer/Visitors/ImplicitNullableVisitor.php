<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\PhpVersion;
use Ylab\PhpMigrater\Fixer\Fixers\ImplicitNullableFixer;

/**
 * Detects implicit nullable parameter types: function f(Type $x = null)
 * This should be written as: function f(?Type $x = null)
 *
 * Deprecated in PHP 8.4.
 */
final class ImplicitNullableVisitor extends NodeVisitorAbstract
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
        if (!$node instanceof Node\FunctionLike) {
            return null;
        }

        foreach ($node->getParams() as $param) {
            if ($param->type === null) {
                continue; // no type declaration
            }
            if ($param->default === null) {
                continue; // no default value
            }

            // Check if default is null
            if (!$param->default instanceof Node\Expr\ConstFetch) {
                continue;
            }
            if (strtolower($param->default->name->toString()) !== 'null') {
                continue;
            }

            // Check if the type already includes null (nullable or union with null)
            if ($param->type instanceof Node\NullableType) {
                continue; // already ?Type
            }
            if ($param->type instanceof Node\UnionType) {
                foreach ($param->type->types as $type) {
                    if ($type instanceof Node\Identifier && strtolower($type->toString()) === 'null') {
                        continue 2; // already Type|null
                    }
                }
            }

            $paramName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name) ? $param->var->name : '?';
            $typeName = $this->getTypeName($param->type);

            $this->issues[] = new Issue(
                file: $this->filePath,
                line: $param->getStartLine(),
                column: 0,
                severity: Severity::Warning,
                message: "Implicit nullable type for parameter \${$paramName} ({$typeName} \${$paramName} = null). Use ?{$typeName} instead.",
                category: IssueCategory::ImplicitNullable,
                affectedFrom: PhpVersion::PHP_84,
                suggestedFixerClass: ImplicitNullableFixer::class,
            );
        }

        return null;
    }

    private function getTypeName(Node $type): string
    {
        if ($type instanceof Node\Identifier) {
            return $type->toString();
        }
        if ($type instanceof Node\Name) {
            return $type->toString();
        }
        return 'mixed';
    }
}
