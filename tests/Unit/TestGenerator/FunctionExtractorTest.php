<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\TestGenerator;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\TestGenerator\ExtractedFunction;
use Ylab\PhpMigrater\TestGenerator\FunctionExtractor;

final class FunctionExtractorTest extends TestCase
{
    private FunctionExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new FunctionExtractor();
    }

    public function testExtractsStandaloneFunction(): void
    {
        $code = '<?php function greet(string $name): string { return "Hi $name"; }';
        $functions = $this->extractor->extract($code);

        $this->assertCount(1, $functions);
        $this->assertSame('greet', $functions[0]->name);
        $this->assertNull($functions[0]->className);
        $this->assertSame('string', $functions[0]->returnType);
        $this->assertFalse($functions[0]->isMethod());
    }

    public function testExtractsClassMethod(): void
    {
        $code = '<?php
class Foo {
    public function bar(int $x): void { }
}';
        $functions = $this->extractor->extract($code);

        $this->assertCount(1, $functions);
        $this->assertSame('bar', $functions[0]->name);
        $this->assertSame('Foo', $functions[0]->className);
        $this->assertTrue($functions[0]->isMethod());
        $this->assertSame('public', $functions[0]->visibility);
    }

    public function testExtractsStaticMethod(): void
    {
        $code = '<?php class Foo { public static function create(): self { return new self(); } }';
        $functions = $this->extractor->extract($code);

        $this->assertCount(1, $functions);
        $this->assertTrue($functions[0]->isStatic);
    }

    public function testExtractsNamespace(): void
    {
        $code = '<?php namespace App\Models; class User { public function save(): bool { return true; } }';
        $functions = $this->extractor->extract($code);

        $this->assertCount(1, $functions);
        $this->assertSame('App\Models', $functions[0]->namespace);
        $this->assertSame('App\Models\User', $functions[0]->getFullClassName());
    }

    public function testExtractsMultipleFunctions(): void
    {
        $code = '<?php
function a(): void { }
function b(): void { }
class C {
    public function d(): void { }
    private function e(): void { }
}';
        $functions = $this->extractor->extract($code);

        $this->assertCount(4, $functions);
    }

    public function testExtractsParams(): void
    {
        $code = '<?php function foo(string $name, int $age = 0): void { }';
        $functions = $this->extractor->extract($code);

        $this->assertCount(1, $functions);
        $params = $functions[0]->params;
        $this->assertCount(2, $params);
        $this->assertSame('$name', $params[0]['name']);
        $this->assertSame('string', $params[0]['type']);
        $this->assertFalse($params[0]['default']);
        $this->assertSame('$age', $params[1]['name']);
        $this->assertTrue($params[1]['default']);
    }

    public function testHandlesInvalidCode(): void
    {
        $code = '<?php this is not valid code {{{';
        $functions = $this->extractor->extract($code);

        $this->assertSame([], $functions);
    }

    public function testExtractsNullableReturnType(): void
    {
        $code = '<?php function foo(): ?string { return null; }';
        $functions = $this->extractor->extract($code);

        $this->assertCount(1, $functions);
        $this->assertSame('?string', $functions[0]->returnType);
    }

    public function testExtractsVisibility(): void
    {
        $code = '<?php class Foo {
    public function pub(): void { }
    protected function prot(): void { }
    private function priv(): void { }
}';
        $functions = $this->extractor->extract($code);

        $this->assertCount(3, $functions);
        $this->assertSame('public', $functions[0]->visibility);
        $this->assertSame('protected', $functions[1]->visibility);
        $this->assertSame('private', $functions[2]->visibility);
    }
}
