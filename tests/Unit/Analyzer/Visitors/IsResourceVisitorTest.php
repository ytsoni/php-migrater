<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer\Visitors;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Visitors\IsResourceVisitor;

final class IsResourceVisitorTest extends TestCase
{
    private IsResourceVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new IsResourceVisitor();
        $this->visitor->setFilePath('test.php');
    }

    public function testDetectsIsResourceCall(): void
    {
        $code = '<?php if (is_resource($ch)) { }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
        $this->assertSame(IssueCategory::ResourceToObject, $issues[0]->category);
    }

    public function testIgnoresOtherFunctions(): void
    {
        $code = '<?php if (is_string($x)) { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testDetectsMultipleCalls(): void
    {
        $code = '<?php
is_resource($a);
is_resource($b);
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
