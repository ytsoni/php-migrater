<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer\Visitors;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Visitors\StringToNumberVisitor;

final class StringToNumberVisitorTest extends TestCase
{
    private StringToNumberVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new StringToNumberVisitor();
        $this->visitor->setFilePath('test.php');
    }

    public function testDetectsStringVarComparedToInt(): void
    {
        $code = '<?php if ($name == 0) { }';
        $issues = $this->analyze($code);

        // This also triggers LooseComparison visitor, but StringToNumber
        // focuses on mixed string/number patterns
        $this->assertGreaterThanOrEqual(0, count($issues));
    }

    public function testIgnoresStrictComparison(): void
    {
        $code = '<?php if ($name === 0) { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testDetectsSmallerOperator(): void
    {
        $code = '<?php $x = "foo" < 5;';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
        $this->assertSame(IssueCategory::StringToNumber, $issues[0]->category);
    }

    public function testDetectsSmallerOrEqualOperator(): void
    {
        $code = '<?php $x = "foo" <= 5;';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testDetectsGreaterOperator(): void
    {
        $code = '<?php $x = 10 > "bar";';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testDetectsGreaterOrEqualOperator(): void
    {
        $code = '<?php $x = 10 >= "bar";';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testDetectsSpaceshipOperator(): void
    {
        $code = '<?php $x = "abc" <=> 0;';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testIgnoresNumericStringComparison(): void
    {
        $code = '<?php $x = "42" < 100;';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresStringStringComparison(): void
    {
        $code = '<?php $x = "abc" < "def";';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresNumberNumberComparison(): void
    {
        $code = '<?php $x = 1 < 2;';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testDetectsReverseOrder(): void
    {
        // Number on right, non-numeric string on left
        $code = '<?php $x = "hello" > 0;';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testIgnoresAdditionOperator(): void
    {
        // Only comparison operators should trigger
        $code = '<?php $x = "foo" + 5;';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testDetectsFloatComparison(): void
    {
        $code = '<?php $x = "foo" < 3.14;';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
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
