<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Reporter;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\PhpVersion;
use Ylab\PhpMigrater\Reporter\ConsoleReporter;
use Ylab\PhpMigrater\Reporter\HtmlReporter;
use Ylab\PhpMigrater\Reporter\JsonReporter;
use Ylab\PhpMigrater\Reporter\MigrationReport;

final class ReporterTest extends TestCase
{
    private MigrationReport $report;

    protected function setUp(): void
    {
        $this->report = new MigrationReport(PhpVersion::PHP_56, PhpVersion::PHP_81);
        $this->report->setFilesAnalyzed(10);

        $this->report->addFileIssues('src/foo.php', [
            new Issue('src/foo.php', 5, 1, Severity::Error, 'Loose comparison', IssueCategory::LooseComparison),
            new Issue('src/foo.php', 12, 1, Severity::Warning, 'Curly braces', IssueCategory::CurlyBraceAccess),
        ]);

        $this->report->addFileIssues('src/bar.php', [
            new Issue('src/bar.php', 1, 1, Severity::Info, 'Dynamic prop', IssueCategory::DynamicProperty),
        ]);

        $this->report->finish();
    }

    public function testMigrationReportCounts(): void
    {
        $this->assertSame(10, $this->report->getFilesAnalyzed());
        $this->assertSame(3, $this->report->getTotalIssueCount());
        $this->assertSame(2, $this->report->getFilesWithIssues());
    }

    public function testSeverityCounts(): void
    {
        $counts = $this->report->getIssueSeverityCounts();
        $this->assertSame(1, $counts['error']);
        $this->assertSame(1, $counts['warning']);
        $this->assertSame(1, $counts['info']);
    }

    public function testCategoryCounts(): void
    {
        $cats = $this->report->getIssueCategoryCounts();
        $this->assertArrayHasKey('loose_comparison', $cats);
        $this->assertArrayHasKey('curly_brace_access', $cats);
        $this->assertArrayHasKey('dynamic_property', $cats);
    }

    public function testConsoleReporterOutput(): void
    {
        $reporter = new ConsoleReporter();
        $output = $reporter->render($this->report);

        $this->assertStringContainsString('PHP 5.6', $output);
        $this->assertStringContainsString('PHP 8.1', $output);
        $this->assertStringContainsString('Files analyzed', $output);
        $this->assertStringContainsString('Total issues', $output);
    }

    public function testJsonReporterOutput(): void
    {
        $reporter = new JsonReporter();
        $output = $reporter->render($this->report);
        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertSame('5.6', $data['migration']['source']);
        $this->assertSame('8.1', $data['migration']['target']);
        $this->assertSame(10, $data['summary']['filesAnalyzed']);
        $this->assertSame(3, $data['summary']['totalIssues']);
    }

    public function testHtmlReporterOutput(): void
    {
        $reporter = new HtmlReporter();
        $output = $reporter->render($this->report);

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('PHP 5.6', $output);
        $this->assertStringContainsString('PHP 8.1', $output);
        $this->assertStringContainsString('src/foo.php', $output);
    }

    public function testReporterNames(): void
    {
        $this->assertSame('console', (new ConsoleReporter())->getName());
        $this->assertSame('json', (new JsonReporter())->getName());
        $this->assertSame('html', (new HtmlReporter())->getName());
    }
}
