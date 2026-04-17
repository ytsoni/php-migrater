<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\LooseComparisonFixer;

final class LooseComparisonFixerTest extends TestCase
{
    private LooseComparisonFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new LooseComparisonFixer();
    }

    public function testName(): void
    {
        $this->assertSame('loose_comparison', $this->fixer->getName());
    }

    public function testSupportsLooseComparison(): void
    {
        $issue = $this->makeIssue(IssueCategory::LooseComparison);
        $this->assertTrue($this->fixer->supports($issue));
    }

    public function testDoesNotSupportOtherCategory(): void
    {
        $issue = $this->makeIssue(IssueCategory::DynamicProperty);
        $this->assertFalse($this->fixer->supports($issue));
    }

    public function testFixesEqualToIdentical(): void
    {
        $code = '<?php if (0 == $a) { }';
        $issues = [$this->makeIssue(IssueCategory::LooseComparison, 1)];
        $result = $this->fixer->fix($code, $issues);

        $this->assertStringContainsString('===', $result);
        $this->assertStringNotContainsString(' == ', $result);
    }

    public function testFixesNotEqualToNotIdentical(): void
    {
        $code = '<?php if ($a != 0) { }';
        $issues = [$this->makeIssue(IssueCategory::LooseComparison, 1)];
        $result = $this->fixer->fix($code, $issues);

        $this->assertStringContainsString('!==', $result);
    }

    public function testNoChangeWithEmptyIssues(): void
    {
        $code = '<?php if (0 == $a) { }';
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
