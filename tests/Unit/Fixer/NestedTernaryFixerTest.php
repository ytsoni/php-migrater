<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\NestedTernaryFixer;

final class NestedTernaryFixerTest extends TestCase
{
    private NestedTernaryFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new NestedTernaryFixer();
    }

    public function testName(): void
    {
        $this->assertSame('nested_ternary', $this->fixer->getName());
    }

    public function testSupportsNestedTernary(): void
    {
        $issue = $this->makeIssue(IssueCategory::NestedTernary);
        $this->assertTrue($this->fixer->supports($issue));
    }

    public function testDoesNotSupportOtherCategory(): void
    {
        $issue = $this->makeIssue(IssueCategory::LooseComparison);
        $this->assertFalse($this->fixer->supports($issue));
    }

    public function testNoChangeWithEmptyIssues(): void
    {
        $code = '<?php $x = $a ? $b : $c ? $d : $e;';
        $result = $this->fixer->fix($code, []);

        $this->assertSame($code, $result);
    }

    public function testFixWrapsNestedTernaryInParentheses(): void
    {
        $code = '<?php $x = $a ? $b : $c ? $d : $e;';
        $issue = $this->makeIssue(IssueCategory::NestedTernary);
        $result = $this->fixer->fix($code, [$issue]);

        $this->assertStringContainsString('(', $result);
        $this->assertNotSame($code, $result);
    }

    public function testFixPreservesSimpleTernary(): void
    {
        $code = '<?php $x = $a ? $b : $c;';
        $issue = $this->makeIssue(IssueCategory::NestedTernary);
        $result = $this->fixer->fix($code, [$issue]);

        // Simple ternary — regex shouldn't match nested pattern
        $this->assertSame($code, $result);
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->fixer->getDescription());
    }

    public function testGetPriority(): void
    {
        $this->assertSame(70, $this->fixer->getPriority());
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
