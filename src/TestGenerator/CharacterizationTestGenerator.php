<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

/**
 * Generates characterization tests that capture current behavior of functions before migration.
 * These tests call each public function/method and assert it does not throw.
 */
final class CharacterizationTestGenerator implements TestGeneratorInterface
{
    private readonly FunctionExtractor $extractor;

    public function __construct()
    {
        $this->extractor = new FunctionExtractor();
    }

    public function getName(): string
    {
        return 'characterization';
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
        $testClassName = $baseName . 'CharacterizationTest';

        $methods = '';
        foreach ($functions as $fn) {
            $methodName = 'test' . ucfirst($fn->name);
            $args = $this->generateDummyArgs($fn->params);

            $methods .= <<<PHP

    public function {$methodName}(): void
    {
        // Characterization test: captures current behavior
        \$this->expectNotToPerformAssertions();
        {$fn->name}({$args});
    }

PHP;
        }

        $requirePath = $this->makeRelativePath($filePath);
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
            targetFilePath: $this->computeTestPath($filePath, $testClassName),
            generatorName: $this->getName(),
        );
    }

    /**
     * @param array<ExtractedFunction> $methods
     */
    private function generateClassTests(string $className, array $methods, string $filePath): ?GeneratedTest
    {
        $shortClass = substr($className, strrpos($className, '\\') + 1);
        $testClassName = $shortClass . 'CharacterizationTest';
        $namespace = substr($className, 0, strrpos($className, '\\') ?: 0);

        $publicMethods = array_filter($methods, fn($m) => $m->visibility === 'public' && $m->name !== '__construct');

        if (empty($publicMethods)) {
            return null;
        }

        $testMethods = '';
        foreach ($publicMethods as $fn) {
            $methodName = 'test' . ucfirst($fn->name);
            $args = $this->generateDummyArgs($fn->params);

            if ($fn->isStatic) {
                $call = "\\{$className}::{$fn->name}({$args})";
            } else {
                $call = "\$this->createInstance()->{$fn->name}({$args})";
            }

            $testMethods .= <<<PHP

    public function {$methodName}(): void
    {
        \$this->expectNotToPerformAssertions();
        {$call};
    }

PHP;
        }

        $constructorArgs = '';
        $constructors = array_filter($methods, fn($m) => $m->name === '__construct');
        if (!empty($constructors)) {
            $ctor = reset($constructors);
            $constructorArgs = $this->generateDummyArgs($ctor->params);
        }

        $useStatement = $namespace ? "use {$className};" : '';

        $code = <<<PHP
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
{$useStatement}

class {$testClassName} extends TestCase
{
{$testMethods}
    private function createInstance(): {$shortClass}
    {
        return new {$shortClass}({$constructorArgs});
    }
}

PHP;

        return new GeneratedTest(
            testClassName: $testClassName,
            testCode: $code,
            targetFilePath: $this->computeTestPath($filePath, $testClassName),
            generatorName: $this->getName(),
        );
    }

    /**
     * @param array<array{name: string, type: ?string, default: bool}> $params
     */
    private function generateDummyArgs(array $params): string
    {
        $args = [];
        foreach ($params as $param) {
            if ($param['default']) {
                continue; // Skip params with defaults
            }
            $args[] = match ($param['type']) {
                'int', 'integer' => '0',
                'float', 'double' => '0.0',
                'string' => "''",
                'bool', 'boolean' => 'false',
                'array' => '[]',
                'callable' => "fn() => null",
                null, 'mixed' => 'null',
                default => $this->guessDummyForType($param['type']),
            };
        }

        return implode(', ', $args);
    }

    private function guessDummyForType(string $type): string
    {
        if (str_starts_with($type, '?')) {
            return 'null';
        }
        if (str_contains($type, '|')) {
            return 'null';
        }
        // For class types, try to instantiate or use null
        return 'null';
    }

    private function makeRelativePath(string $filePath): string
    {
        return str_replace('\\', '/', $filePath);
    }

    private function computeTestPath(string $sourceFile, string $testClassName): string
    {
        $dir = dirname($sourceFile);
        return $dir . '/tests/' . $testClassName . '.php';
    }
}
