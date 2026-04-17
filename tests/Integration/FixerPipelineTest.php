<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\CurlyBraceAccessFixer;
use Ylab\PhpMigrater\Fixer\Fixers\ImplicitNullableFixer;
use Ylab\PhpMigrater\Fixer\Fixers\LooseComparisonFixer;

final class FixerPipelineTest extends TestCase
{
    public function testLooseComparisonFixProducesStrictComparison(): void
    {
        $code = '<?php
if (0 == $val) {
    echo "match";
}
';
        $issues = [
            new Issue('test.php', 2, 0, Severity::Warning, 'Loose comparison', IssueCategory::LooseComparison),
        ];

        $fixer = new LooseComparisonFixer();
        $result = $fixer->fix($code, $issues);

        $this->assertStringContainsString('===', $result);
        $this->assertStringNotContainsString(' == ', $result);
    }

    public function testImplicitNullableFixProducesExplicitNullable(): void
    {
        $code = '<?php
function greet(string $name = null): string {
    return "Hello " . ($name ?? "world");
}
';
        $issues = [
            new Issue('test.php', 2, 0, Severity::Warning, 'Implicit nullable', IssueCategory::ImplicitNullable),
        ];

        $fixer = new ImplicitNullableFixer();
        $result = $fixer->fix($code, $issues);

        $this->assertStringContainsString('?string', $result);
    }

    public function testCurlyBraceFixProducesBracketAccess(): void
    {
        $code = '<?php
$str = "hello";
$first = $str{0};
';
        $issues = [
            new Issue('test.php', 3, 0, Severity::Warning, 'Curly brace access', IssueCategory::CurlyBraceAccess),
        ];

        $fixer = new CurlyBraceAccessFixer();
        $result = $fixer->fix($code, $issues);

        $this->assertStringContainsString('$str[0]', $result);
        $this->assertStringNotContainsString('{0}', $result);
    }

    public function testChainedFixersApplySequentially(): void
    {
        $code = '<?php
function process(array $data = null) {
    if (0 == count($data ?? [])) {
        return;
    }
}
';
        // First fix implicit nullable
        $nullableIssues = [
            new Issue('test.php', 2, 0, Severity::Warning, 'Implicit nullable', IssueCategory::ImplicitNullable),
        ];
        $nullableFixer = new ImplicitNullableFixer();
        $result = $nullableFixer->fix($code, $nullableIssues);

        // Find the actual line of '==' in the reprinted code
        $lines = explode("\n", $result);
        $eqLine = 0;
        foreach ($lines as $i => $line) {
            if (str_contains($line, '==') && !str_contains($line, '===')) {
                $eqLine = $i + 1;
                break;
            }
        }
        $this->assertGreaterThan(0, $eqLine, 'Should find == in intermediate result');

        // Then fix loose comparison
        $looseIssues = [
            new Issue('test.php', $eqLine, 0, Severity::Warning, 'Loose comparison', IssueCategory::LooseComparison),
        ];
        $looseFixer = new LooseComparisonFixer();
        $result = $looseFixer->fix($result, $looseIssues);

        $this->assertStringContainsString('?array', $result);
        $this->assertStringContainsString('===', $result);
    }
}
