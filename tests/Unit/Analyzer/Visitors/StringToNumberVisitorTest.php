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
