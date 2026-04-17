<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer\Visitors;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Visitors\LooseComparisonVisitor;

final class LooseComparisonVisitorTest extends TestCase
{
    private LooseComparisonVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new LooseComparisonVisitor();
        $this->visitor->setFilePath('test.php');
    }

    public function testDetectsZeroEqualsString(): void
    {
        $code = '<?php if (0 == "foo") { }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
        $this->assertSame(IssueCategory::LooseComparison, $issues[0]->category);
        $this->assertStringContainsString('==', $issues[0]->message);
    }

    public function testDetectsEmptyStringEqualsVar(): void
    {
        $code = '<?php if ("" == $val) { }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testDetectsNotEqualWithZero(): void
    {
        $code = '<?php if ($x != 0) { }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
        $this->assertStringContainsString('!=', $issues[0]->message);
    }

    public function testDetectsFalseComparison(): void
    {
        $code = '<?php if (false == $val) { }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testDetectsNullComparison(): void
    {
        $code = '<?php if (null == $val) { }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testIgnoresStrictComparison(): void
    {
        $code = '<?php if (0 === "foo") { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresSafeLooseComparison(): void
    {
        $code = '<?php if ($a == $b) { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testDetectsMultipleIssues(): void
    {
        $code = '<?php
if (0 == $a) { }
if ("" == $b) { }
if ($c === 0) { }
';
        $issues = $this->analyze($code);

        $this->assertCount(2, $issues);
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
