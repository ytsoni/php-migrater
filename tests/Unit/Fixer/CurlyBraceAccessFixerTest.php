<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\CurlyBraceAccessFixer;

final class CurlyBraceAccessFixerTest extends TestCase
{
    private CurlyBraceAccessFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new CurlyBraceAccessFixer();
    }

    public function testName(): void
    {
        $this->assertSame('curly_brace_access', $this->fixer->getName());
    }

    public function testPriority(): void
    {
        $this->assertSame(80, $this->fixer->getPriority());
    }

    public function testSupportsMatchingIssue(): void
    {
        $issue = $this->createIssue(IssueCategory::CurlyBraceAccess);
        $this->assertTrue($this->fixer->supports($issue));
    }

    public function testDoesNotSupportOtherCategory(): void
    {
        $issue = $this->createIssue(IssueCategory::LooseComparison);
        $this->assertFalse($this->fixer->supports($issue));
    }

    public function testFixSimpleStringAccess(): void
    {
        $code = '<?php $char = $str{0};';
        $issues = [$this->createIssue(IssueCategory::CurlyBraceAccess)];

        $fixed = $this->fixer->fix($code, $issues);

        $this->assertSame('<?php $char = $str[0];', $fixed);
    }

    public function testFixVariableIndex(): void
    {
        $code = '<?php $val = $arr{$key};';
        $issues = [$this->createIssue(IssueCategory::CurlyBraceAccess)];

        $fixed = $this->fixer->fix($code, $issues);

        $this->assertSame('<?php $val = $arr[$key];', $fixed);
    }

    public function testNoChangeWithoutIssues(): void
    {
        $code = '<?php $val = $arr[0];';
        $this->assertSame($code, $this->fixer->fix($code, []));
    }

    private function createIssue(IssueCategory $category): Issue
    {
        return new Issue(
            file: 'test.php',
            line: 1,
            column: 1,
            severity: Severity::Warning,
            message: 'test',
            category: $category,
        );
    }
}
