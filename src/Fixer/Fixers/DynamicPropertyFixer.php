<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer\Fixers;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Fixer\FixerInterface;

/**
 * Adds #[\AllowDynamicProperties] attribute to classes using dynamic properties.
 * Dynamic properties are deprecated in PHP 8.2.
 */
final class DynamicPropertyFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'dynamic_property';
    }

    public function getDescription(): string
    {
        return 'Add #[\\AllowDynamicProperties] attribute to classes with dynamic properties';
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function supports(Issue $issue): bool
    {
        return $issue->category === IssueCategory::DynamicProperty;
    }

    public function fix(string $sourceCode, array $issues): string
    {
        if (empty($issues)) {
            return $sourceCode;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        try {
            $ast = $parser->parse($sourceCode);
        } catch (\PhpParser\Error) {
            return $sourceCode;
        }

        if ($ast === null) {
            return $sourceCode;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class() extends NodeVisitorAbstract {
            public function enterNode(Node $node): ?int
            {
                if (!$node instanceof Node\Stmt\Class_) {
                    return null;
                }

                // Check if already has #[AllowDynamicProperties]
                foreach ($node->attrGroups as $attrGroup) {
                    foreach ($attrGroup->attrs as $attr) {
                        if ($attr->name->toString() === 'AllowDynamicProperties'
                            || $attr->name->toString() === '\\AllowDynamicProperties'
                        ) {
                            return null;
                        }
                    }
                }

                // Add the attribute
                $attr = new Node\Attribute(
                    new Node\Name\FullyQualified('AllowDynamicProperties'),
                );
                array_unshift($node->attrGroups, new Node\AttributeGroup([$attr]));

                return null;
            }
        });

        $ast = $traverser->traverse($ast);
        $printer = new Standard();

        return $printer->prettyPrintFile($ast);
    }
}
