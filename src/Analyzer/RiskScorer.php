<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\ParserFactory;

/**
 * Scores files by migration risk based on issues and code complexity.
 *
 * Risk = (critical * 10 + warnings * 3 + infos * 1) * complexity_factor
 * Complexity estimated by AST node count and cyclomatic complexity.
 */
final class RiskScorer
{
    /**
     * @param array<string, Issue[]> $issuesByFile file path => issues
     */
    public function score(array $issuesByFile): RiskReport
    {
        $report = new RiskReport();

        foreach ($issuesByFile as $filePath => $issues) {
            $complexityFactor = $this->estimateComplexity($filePath);
            $issueScore = 0;
            $categories = [];

            foreach ($issues as $issue) {
                $issueScore += $issue->severity->weight();
                $cat = $issue->category->value;
                $categories[$cat] = ($categories[$cat] ?? 0) + 1;
            }

            $riskScore = $issueScore * $complexityFactor;

            $report->addFileRisk(
                filePath: $filePath,
                riskScore: $riskScore,
                issueCount: count($issues),
                categories: $categories,
            );
        }

        return $report;
    }

    private function estimateComplexity(string $filePath): float
    {
        if (!file_exists($filePath)) {
            return 1.0;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return 1.0;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        try {
            $ast = $parser->parse($code);
        } catch (\PhpParser\Error) {
            return 1.0;
        }

        if ($ast === null) {
            return 1.0;
        }

        $visitor = new ComplexityVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $nodeCount = $visitor->getNodeCount();
        $cyclomatic = $visitor->getCyclomaticComplexity();

        // Normalize: small files (< 50 nodes) = 1.0, large (500+) = 2.0
        $sizeFactor = min(2.0, max(1.0, $nodeCount / 250.0));

        // Cyclomatic complexity: simple (< 5) = 1.0, complex (20+) = 2.0
        $complexityFactor = min(2.0, max(1.0, $cyclomatic / 10.0));

        return ($sizeFactor + $complexityFactor) / 2.0;
    }
}

/** @internal */
final class ComplexityVisitor extends NodeVisitorAbstract
{
    private int $nodeCount = 0;
    private int $cyclomaticComplexity = 1;

    public function enterNode(Node $node): ?int
    {
        $this->nodeCount++;

        // Count decision points for cyclomatic complexity
        if ($node instanceof Node\Stmt\If_
            || $node instanceof Node\Stmt\ElseIf_
            || $node instanceof Node\Stmt\Case_
            || $node instanceof Node\Stmt\Catch_
            || $node instanceof Node\Stmt\While_
            || $node instanceof Node\Stmt\Do_
            || $node instanceof Node\Stmt\For_
            || $node instanceof Node\Stmt\Foreach_
            || $node instanceof Node\Expr\Ternary
            || $node instanceof Node\Expr\BinaryOp\BooleanAnd
            || $node instanceof Node\Expr\BinaryOp\BooleanOr
            || $node instanceof Node\Expr\BinaryOp\LogicalAnd
            || $node instanceof Node\Expr\BinaryOp\LogicalOr
            || $node instanceof Node\Expr\BinaryOp\Coalesce
            || $node instanceof Node\Expr\Match_
        ) {
            $this->cyclomaticComplexity++;
        }

        return null;
    }

    public function getNodeCount(): int
    {
        return $this->nodeCount;
    }

    public function getCyclomaticComplexity(): int
    {
        return $this->cyclomaticComplexity;
    }
}
