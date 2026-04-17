<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\PhpVersion;
use Ylab\PhpMigrater\Fixer\Fixers\DynamicPropertyFixer;

/**
 * Detects dynamic property access on classes that don't declare them.
 * Deprecated in PHP 8.2 (triggers deprecation notice), will become an error in PHP 9.0.
 *
 * We flag all property fetches on $this where we can detect the class doesn't
 * declare the property. This is a heuristic — it may produce false positives for
 * classes using __get/__set.
 */
final class DynamicPropertyVisitor extends NodeVisitorAbstract
{
    /** @var Issue[] */
    private array $issues = [];
    private string $filePath = '';

    /** @var array<string, list<string>> class name => declared properties */
    private array $classProperties = [];
    private ?string $currentClass = null;

    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
        $this->issues = [];
        $this->classProperties = [];
        $this->currentClass = null;
    }

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function enterNode(Node $node): ?int
    {
        // Track class declarations and their properties
        if ($node instanceof Node\Stmt\Class_) {
            $className = $node->namespacedName?->toString() ?? $node->name?->toString() ?? '<anonymous>';
            $this->currentClass = $className;
            $this->classProperties[$className] = [];

            // Check if class has #[AllowDynamicProperties] or uses __get/__set
            foreach ($node->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($attr->name->toString() === 'AllowDynamicProperties') {
                        // Class explicitly allows dynamic properties
                        return null;
                    }
                }
            }

            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Property) {
                    foreach ($stmt->props as $prop) {
                        $this->classProperties[$className][] = $prop->name->toString();
                    }
                }
                if ($stmt instanceof Node\Stmt\ClassMethod) {
                    $methodName = $stmt->name->toString();
                    if ($methodName === '__get' || $methodName === '__set') {
                        // Has magic methods, skip dynamic property detection for this class
                        $this->classProperties[$className] = null; // marker for "skip"
                        return null;
                    }
                }
            }
        }

        // Track property declarations in constructor promotion
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === '__construct' && $this->currentClass !== null) {
            foreach ($node->params as $param) {
                if ($param->flags !== 0) { // promoted property
                    $this->classProperties[$this->currentClass][] = $param->var->name;
                }
            }
        }

        // Detect $this->undeclaredProp assignments
        if ($node instanceof Node\Expr\Assign
            && $node->var instanceof Node\Expr\PropertyFetch
            && $node->var->var instanceof Node\Expr\Variable
            && $node->var->var->name === 'this'
            && $node->var->name instanceof Node\Identifier
            && $this->currentClass !== null
        ) {
            $propName = $node->var->name->toString();
            $declaredProps = $this->classProperties[$this->currentClass] ?? null;

            if ($declaredProps !== null && !in_array($propName, $declaredProps, true)) {
                $this->issues[] = new Issue(
                    file: $this->filePath,
                    line: $node->getStartLine(),
                    column: 0,
                    severity: Severity::Warning,
                    message: "Dynamic property \${$this->currentClass}->\${$propName} is deprecated in PHP 8.2.",
                    category: IssueCategory::DynamicProperty,
                    affectedFrom: PhpVersion::PHP_82,
                    suggestedFixerClass: DynamicPropertyFixer::class,
                );
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->currentClass = null;
        }
        return null;
    }
}
