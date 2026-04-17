<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\ResourceToObjectFixer;

final class ResourceToObjectFixerTest extends TestCase
{
    private ResourceToObjectFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new ResourceToObjectFixer();
    }

    public function testName(): void
    {
        $this->assertSame('resource_to_object', $this->fixer->getName());
    }

    public function testSupportsResourceToObject(): void
    {
        $issue = $this->makeIssue(IssueCategory::ResourceToObject);
        $this->assertTrue($this->fixer->supports($issue));
    }

    public function testReplacesIsResourceWithInstanceof(): void
    {
        $code = '<?php if (is_resource($ch)) { }';
        $issues = [$this->makeIssue(IssueCategory::ResourceToObject, 1)];
        $result = $this->fixer->fix($code, $issues);

        $this->assertStringContainsString('instanceof', $result);
        $this->assertStringContainsString('CurlHandle', $result);
    }

    public function testNoChangeWithEmptyIssues(): void
    {
        $code = '<?php if (is_resource($ch)) { }';
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
