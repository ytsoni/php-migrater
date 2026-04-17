<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer\Visitors;

use PHPUnit\Framework\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Visitors\DynamicPropertyVisitor;

final class DynamicPropertyVisitorTest extends TestCase
{
    private DynamicPropertyVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new DynamicPropertyVisitor();
        $this->visitor->setFilePath('test.php');
    }

    public function testDetectsUndeclaredPropertyAssignment(): void
    {
        $code = '<?php
class Foo {
    public function init() {
        $this->undeclared = "value";
    }
}';
        $issues = $this->analyze($code);

        $this->assertCount(1, $issues);
        $this->assertSame(IssueCategory::DynamicProperty, $issues[0]->category);
        $this->assertStringContainsString('undeclared', $issues[0]->message);
    }

    public function testIgnoresDeclaredProperty(): void
    {
        $code = '<?php
class Foo {
    public string $name;
    public function init() {
        $this->name = "value";
    }
}';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresClassWithAllowDynamicProperties(): void
    {
        $code = '<?php
#[\AllowDynamicProperties]
class Foo {
    public function init() {
        $this->dynamic = "value";
    }
}';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresClassWithMagicGet(): void
    {
        $code = '<?php
class Foo {
    public function __get($name) { return null; }
    public function init() {
        $this->dynamic = "value";
    }
}';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testIgnoresClassWithMagicSet(): void
    {
        $code = '<?php
class Foo {
    public function __set($name, $value) { }
    public function init() {
        $this->dynamic = "value";
    }
}';
        $issues = $this->analyze($code);

        $this->assertCount(0, $issues);
    }

    public function testRecognizesConstructorPromotion(): void
    {
        $code = '<?php
class Foo {
    public function __construct(private string $name) {}
    public function init() {
        $this->name = "value";
    }
}';
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
