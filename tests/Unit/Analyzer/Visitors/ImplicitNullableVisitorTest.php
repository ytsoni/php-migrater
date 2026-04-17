<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer\Visitors;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Visitors\ImplicitNullableVisitor;

final class ImplicitNullableVisitorTest extends TestCase
{
    private ImplicitNullableVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ImplicitNullableVisitor();
        $this->visitor->setFilePath('test.php');
    }

    public function testDetectsImplicitNullable(): void
    {
        $code = '<?php function foo(string $name = null) { }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
        $this->assertSame(IssueCategory::ImplicitNullable, $issues[0]->category);
        $this->assertStringContainsString('$name', $issues[0]->message);
    }

    public function testDetectsInClassMethod(): void
    {
        $code = '<?php class Foo { public function bar(array $data = null) { } }';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
    }

    public function testIgnoresExplicitNullable(): void
    {
        $code = '<?php function foo(?string $name = null) { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresUnionWithNull(): void
    {
        $code = '<?php function foo(string|null $name = null) { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresNoDefault(): void
    {
        $code = '<?php function foo(string $name) { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresNonNullDefault(): void
    {
        $code = '<?php function foo(string $name = "world") { }';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresUntypedParameter(): void
    {
        $code = '<?php function foo($name = null) { }';
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
