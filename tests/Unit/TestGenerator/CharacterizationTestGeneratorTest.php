<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\TestGenerator;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\TestGenerator\CharacterizationTestGenerator;

final class CharacterizationTestGeneratorTest extends TestCase
{
    private CharacterizationTestGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new CharacterizationTestGenerator();
    }

    public function testName(): void
    {
        $this->assertSame('characterization', $this->generator->getName());
    }

    public function testGeneratesTestForStandaloneFunction(): void
    {
        $code = '<?php function add(int $a, int $b): int { return $a + $b; }';
        $tests = $this->generator->generate($code, '/tmp/math.php');

        $this->assertNotEmpty($tests);
        $this->assertStringContainsString('CharacterizationTest', $tests[0]->testClassName);
        $this->assertStringContainsString('testAdd', $tests[0]->testCode);
    }

    public function testGeneratesTestForClassMethod(): void
    {
        $code = '<?php class Calculator {
    public function sum(int $a, int $b): int { return $a + $b; }
}';
        $tests = $this->generator->generate($code, '/tmp/Calculator.php');

        $this->assertNotEmpty($tests);
        $this->assertStringContainsString('TestCase', $tests[0]->testCode);
    }

    public function testEmptyCodeProducesNoTests(): void
    {
        $code = '<?php // empty file';
        $tests = $this->generator->generate($code, '/tmp/empty.php');

        $this->assertEmpty($tests);
    }
}
