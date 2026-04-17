<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\Fixers\DynamicPropertyFixer;

final class DynamicPropertyFixerTest extends TestCase
{
    private DynamicPropertyFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new DynamicPropertyFixer();
    }

    public function testName(): void
    {
        $this->assertSame('dynamic_property', $this->fixer->getName());
    }

    public function testSupportsDynamicProperty(): void
    {
        $issue = $this->makeIssue(IssueCategory::DynamicProperty);
        $this->assertTrue($this->fixer->supports($issue));
    }

    public function testAddsAllowDynamicPropertiesAttribute(): void
    {
        $code = '<?php class Foo { public function init() { $this->x = 1; } }';
        $issues = [$this->makeIssue(IssueCategory::DynamicProperty, 1)];
        $result = $this->fixer->fix($code, $issues);

        $this->assertStringContainsString('AllowDynamicProperties', $result);
    }

    public function testDoesNotDuplicateAttribute(): void
    {
        $code = '<?php
#[\AllowDynamicProperties]
class Foo { public function init() { $this->x = 1; } }';
        $issues = [$this->makeIssue(IssueCategory::DynamicProperty, 3)];
        $result = $this->fixer->fix($code, $issues);

        // Only one occurrence of the attribute
        $this->assertSame(1, substr_count($result, 'AllowDynamicProperties'));
    }

    public function testNoChangeWithEmptyIssues(): void
    {
        $code = '<?php class Foo { }';
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
