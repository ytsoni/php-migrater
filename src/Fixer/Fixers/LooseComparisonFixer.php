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
 * Replaces loose comparisons (== / !=) with strict (=== / !==)
 * where it can be statically determined to be safe.
 */
final class LooseComparisonFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'loose_comparison';
    }

    public function getDescription(): string
    {
        return 'Replace == with === and != with !== where safe';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function supports(Issue $issue): bool
    {
        return $issue->category === IssueCategory::LooseComparison;
    }

    public function fix(string $sourceCode, array $issues): string
    {
        if (empty($issues)) {
            return $sourceCode;
        }

        $affectedLines = array_map(fn(Issue $i) => $i->line, $issues);

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
        $traverser->addVisitor(new class($affectedLines) extends NodeVisitorAbstract {
            /** @param list<int> $lines */
            public function __construct(private readonly array $lines) {}

            public function leaveNode(Node $node): ?Node
            {
                if (!in_array($node->getStartLine(), $this->lines, true)) {
                    return null;
                }

                if ($node instanceof Node\Expr\BinaryOp\Equal) {
                    return new Node\Expr\BinaryOp\Identical($node->left, $node->right, $node->getAttributes());
                }

                if ($node instanceof Node\Expr\BinaryOp\NotEqual) {
                    return new Node\Expr\BinaryOp\NotIdentical($node->left, $node->right, $node->getAttributes());
                }

                return null;
            }
        });

        try {
            $ast = $traverser->traverse($ast);
        } catch (\Error) {
            return $sourceCode;
        }
        $printer = new Standard();

        return $printer->prettyPrintFile($ast);
    }
}
