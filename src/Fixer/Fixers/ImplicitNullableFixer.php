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
 * Converts implicit nullable types to explicit nullable types.
 * function f(Type $x = null) → function f(?Type $x = null)
 *
 * Deprecated in PHP 8.4.
 */
final class ImplicitNullableFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'implicit_nullable';
    }

    public function getDescription(): string
    {
        return 'Convert implicit nullable parameters to explicit ?Type syntax';
    }

    public function getPriority(): int
    {
        return 60;
    }

    public function supports(Issue $issue): bool
    {
        return $issue->category === IssueCategory::ImplicitNullable;
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
                if (!$node instanceof Node\FunctionLike) {
                    return null;
                }

                foreach ($node->getParams() as $param) {
                    if ($param->type === null || $param->default === null) {
                        continue;
                    }

                    // Check if default is null
                    if (!$param->default instanceof Node\Expr\ConstFetch
                        || strtolower($param->default->name->toString()) !== 'null'
                    ) {
                        continue;
                    }

                    // Skip if already nullable
                    if ($param->type instanceof Node\NullableType) {
                        continue;
                    }
                    if ($param->type instanceof Node\UnionType) {
                        foreach ($param->type->types as $type) {
                            if ($type instanceof Node\Identifier && strtolower($type->toString()) === 'null') {
                                continue 2;
                            }
                        }
                    }

                    // Make it nullable
                    if ($param->type instanceof Node\Identifier || $param->type instanceof Node\Name) {
                        $param->type = new Node\NullableType($param->type);
                    }
                }

                return null;
            }
        });

        $ast = $traverser->traverse($ast);
        $printer = new Standard();

        return $printer->prettyPrintFile($ast);
    }
}
