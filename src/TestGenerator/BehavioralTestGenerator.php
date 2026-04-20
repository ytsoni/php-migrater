<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

/**
 * Generates behavioral tests that test expected input/output contracts.
 * Focuses on public methods and returns type-based assertion stubs.
 */
final class BehavioralTestGenerator implements TestGeneratorInterface
{
    private readonly FunctionExtractor $extractor;

    public function __construct()
    {
        $this->extractor = new FunctionExtractor();
    }

    public function getName(): string
    {
        return 'behavioral';
    }

    public function generate(string $sourceCode, string $filePath): array
    {
        $functions = $this->extractor->extract($sourceCode);

        if (empty($functions)) {
            return [];
        }

        // Group by class
        $grouped = ['__standalone__' => []];
        foreach ($functions as $fn) {
            if ($fn->visibility !== 'public') {
                continue;
            }
            if ($fn->name === '__construct') {
                continue;
            }
            if ($fn->isMethod()) {
                $key = $fn->getFullClassName() ?? $fn->className;
                $grouped[$key][] = $fn;
            } else {
                $grouped['__standalone__'][] = $fn;
            }
        }

        $tests = [];

        foreach ($grouped as $key => $fns) {
            if (empty($fns)) {
                continue;
            }

            if ($key === '__standalone__') {
                $test = $this->generateStandaloneTests($fns, $filePath);
            } else {
                $test = $this->generateClassTests($key, $fns, $filePath);
            }

            if ($test !== null) {
                $tests[] = $test;
            }
        }

        return $tests;
    }

    /**
     * @param array<ExtractedFunction> $functions
     */
    private function generateStandaloneTests(array $functions, string $filePath): GeneratedTest
    {
        $baseName = ucfirst(pathinfo($filePath, PATHINFO_FILENAME));
        $testClassName = $baseName . 'BehavioralTest';

        $methods = '';
        foreach ($functions as $fn) {
            $methods .= $this->generateTestMethod($fn);
        }

        $requirePath = str_replace('\\', '/', $filePath);
        $code = <<<PHP
<?php

declare(strict_types=1);

require_once __DIR__ . '/{$requirePath}';

use PHPUnit\Framework\TestCase;

class {$testClassName} extends TestCase
{
{$methods}}

PHP;

        return new GeneratedTest(
            testClassName: $testClassName,
            testCode: $code,
            targetFilePath: dirname($filePath) . '/tests/' . $testClassName . '.php',
            generatorName: $this->getName(),
        );
    }

    /**
     * @param array<ExtractedFunction> $methods
     */
    private function generateClassTests(string $className, array $methods, string $filePath): GeneratedTest
    {
        $pos = strrpos($className, '\\');
        $shortClass = $pos !== false ? substr($className, $pos + 1) : $className;
        $testClassName = $shortClass . 'BehavioralTest';
        $namespace = $pos !== false ? substr($className, 0, $pos) : '';

        $testMethods = '';
        foreach ($methods as $fn) {
            $testMethods .= $this->generateTestMethod($fn, $className);
        }

        $useStatement = $namespace ? "use {$className};" : '';

        $code = <<<PHP
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
{$useStatement}

class {$testClassName} extends TestCase
{
{$testMethods}}

PHP;

        return new GeneratedTest(
            testClassName: $testClassName,
            testCode: $code,
            targetFilePath: dirname($filePath) . '/tests/' . $testClassName . '.php',
            generatorName: $this->getName(),
        );
    }

    private function generateTestMethod(ExtractedFunction $fn, ?string $className = null): string
    {
        $methodName = 'test' . ucfirst($fn->name);
        $returnAssertion = $this->generateReturnAssertion($fn->returnType);

        if ($fn->isMethod() && $className !== null) {
            $pos = strrpos($className, '\\');
            $shortClass = $pos !== false ? substr($className, $pos + 1) : $className;
            if ($fn->isStatic) {
                $call = "\\{$className}::{$fn->name}(/* TODO: provide args */)";
            } else {
                $call = "(new {$shortClass}(/* TODO: constructor args */))->{$fn->name}(/* TODO: provide args */)";
            }
        } else {
            $call = "{$fn->name}(/* TODO: provide args */)";
        }

        $assertion = $returnAssertion
            ? "\$result = {$call};\n        {$returnAssertion}"
            : "{$call};\n        \$this->expectNotToPerformAssertions();";

        return <<<PHP

    public function {$methodName}(): void
    {
        // TODO: Behavioral test - verify expected input/output contract
        {$assertion}
    }

PHP;
    }

    private function generateReturnAssertion(?string $returnType): ?string
    {
        if ($returnType === null || $returnType === 'mixed') {
            return null;
        }

        return match (ltrim($returnType, '?')) {
            'void' => null,
            'bool', 'boolean' => '$this->assertIsBool($result);',
            'int', 'integer' => '$this->assertIsInt($result);',
            'float', 'double' => '$this->assertIsFloat($result);',
            'string' => '$this->assertIsString($result);',
            'array' => '$this->assertIsArray($result);',
            'self', 'static' => '$this->assertInstanceOf(static::class, $result);',
            default => "\$this->assertNotNull(\$result); // Expected: {$returnType}",
        };
    }
}
