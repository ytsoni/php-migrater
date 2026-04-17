<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;

final class IssueTest extends TestCase
{
    public function testConstruction(): void
    {
        $issue = new Issue(
            file: 'test.php',
            line: 10,
            column: 5,
            severity: Severity::Warning,
            message: 'Test issue',
            category: IssueCategory::LooseComparison,
        );

        $this->assertSame('test.php', $issue->file);
        $this->assertSame(10, $issue->line);
        $this->assertSame(5, $issue->column);
        $this->assertSame(Severity::Warning, $issue->severity);
        $this->assertSame('Test issue', $issue->message);
        $this->assertSame(IssueCategory::LooseComparison, $issue->category);
        $this->assertNull($issue->suggestedFixerClass);
    }

    public function testToArray(): void
    {
        $issue = new Issue(
            file: 'src/foo.php',
            line: 42,
            column: 1,
            severity: Severity::Error,
            message: 'Deprecated function',
            category: IssueCategory::DeprecatedFunction,
        );

        $arr = $issue->toArray();

        $this->assertSame('src/foo.php', $arr['file']);
        $this->assertSame(42, $arr['line']);
        $this->assertSame('error', $arr['severity']);
        $this->assertSame('deprecated_function', $arr['category']);
        $this->assertSame('Deprecated function', $arr['message']);
    }

    public function testSeverityWeight(): void
    {
        $this->assertSame(10, Severity::Error->weight());
        $this->assertSame(3, Severity::Warning->weight());
        $this->assertSame(1, Severity::Info->weight());
    }
}
