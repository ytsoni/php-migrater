<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\StringToNumberFixer;

final class StringToNumberFixerTest extends TestCase
{
    private StringToNumberFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new StringToNumberFixer();
    }

    public function testName(): void
    {
        $this->assertSame('string_to_number', $this->fixer->getName());
    }

    public function testSupportsStringToNumber(): void
    {
        $issue = $this->makeIssue(IssueCategory::StringToNumber);
        $this->assertTrue($this->fixer->supports($issue));
    }

    public function testAddsCommentToAffectedLine(): void
    {
        $code = "<?php\nif (\$a == 0) { }";
        $issues = [$this->makeIssue(IssueCategory::StringToNumber, 2)];
        $result = $this->fixer->fix($code, $issues);

        $this->assertStringContainsString('PHP 8.0: comparison behavior changed', $result);
    }

    public function testDoesNotDuplicateComment(): void
    {
        $code = "<?php\nif (\$a == 0) { } /* PHP 8.0: comparison behavior changed */";
        $issues = [$this->makeIssue(IssueCategory::StringToNumber, 2)];
        $result = $this->fixer->fix($code, $issues);

        $this->assertSame(1, substr_count($result, 'PHP 8.0: comparison behavior changed'));
    }

    public function testNoChangeWithEmptyIssues(): void
    {
        $code = "<?php\nif (\$a == 0) { }";
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
