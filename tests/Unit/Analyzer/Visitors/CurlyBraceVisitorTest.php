<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer\Visitors;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Visitors\CurlyBraceVisitor;

final class CurlyBraceVisitorTest extends TestCase
{
    private CurlyBraceVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new CurlyBraceVisitor();
        $this->visitor->setFilePath('test.php');
    }

    public function testNoIssuesForBracketAccess(): void
    {
        $code = '<?php $x = $arr[0];';
        $this->visitor->setSourceCode($code);
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testDetectsCurlyBraceAccess(): void
    {
        // php-parser v5 cannot parse $arr{0}, so we simulate by setting
        // source code with { and feeding a bracket-normalized AST
        $sourceWithCurly = '<?php $x = $arr{0};';
        $normalizedCode = '<?php $x = $arr[0];';

        $this->visitor->setSourceCode($sourceWithCurly);
        $issues = $this->analyze($normalizedCode);

        // The visitor checks if '{' appears before the dim start position in source
        // Since we substituted source code, it should find the curly brace
        $this->assertCount(1, $issues);
        $this->assertSame(IssueCategory::CurlyBraceAccess, $issues[0]->category);
    }

    public function testNoIssuesWithoutSourceCode(): void
    {
        $code = '<?php $x = $arr[0];';
        // Do not call setSourceCode — sourceCode stays empty
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testNoIssuesForNonArrayDimFetch(): void
    {
        $code = '<?php $x = 1 + 2;';
        $this->visitor->setSourceCode($code);
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testGetIssuesResetOnSetFilePath(): void
    {
        $sourceWithCurly = '<?php $x = $arr{0};';
        $normalizedCode = '<?php $x = $arr[0];';

        $this->visitor->setSourceCode($sourceWithCurly);
        $this->analyze($normalizedCode);

        // Reset
        $this->visitor->setFilePath('other.php');
        $this->assertCount(0, $this->visitor->getIssues());
    }

    /**
     * @return \Ylab\PhpMigrater\Analyzer\Issue[]
     */
    private function analyze(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this->visitor);
        $traverser->traverse($stmts);

        return $this->visitor->getIssues();
    }
}
