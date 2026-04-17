<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\ImplicitNullableFixer;

final class ImplicitNullableFixerTest extends TestCase
{
    private ImplicitNullableFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new ImplicitNullableFixer();
    }

    public function testName(): void
    {
        $this->assertSame('implicit_nullable', $this->fixer->getName());
    }

    public function testSupportsImplicitNullable(): void
    {
        $issue = $this->makeIssue(IssueCategory::ImplicitNullable);
        $this->assertTrue($this->fixer->supports($issue));
    }

    public function testFixesImplicitNullable(): void
    {
        $code = '<?php function foo(string $name = null) { }';
        $issues = [$this->makeIssue(IssueCategory::ImplicitNullable, 1)];
        $result = $this->fixer->fix($code, $issues);

        $this->assertStringContainsString('?string', $result);
    }

    public function testDoesNotChangeExplicitNullable(): void
    {
        $code = '<?php function foo(?string $name = null) { }';
        $issues = [$this->makeIssue(IssueCategory::ImplicitNullable, 1)];
        $result = $this->fixer->fix($code, $issues);

        // Count occurrences of ? — should still be just one
        $this->assertSame(1, substr_count($result, '?string'));
    }

    public function testNoChangeWithEmptyIssues(): void
    {
        $code = '<?php function foo(string $name = null) { }';
        $result = $this->fixer->fix($code, []);

        $this->assertSame($code, $result);
    }

    private function makeIssue(IssueCategory $category, int $line = 1): Issue
    {
        return new Issue(
            file: 'test.php',
            line: $line,
            column: 0,
            severity: Severity::Warning,
            message: 'test',
            category: $category,
        );
    }
}
