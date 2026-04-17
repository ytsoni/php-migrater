<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\FileRisk;
use Ylab\PhpMigrater\Analyzer\RiskReport;

final class RiskReportTest extends TestCase
{
    public function testAddAndRetrieveFileRisk(): void
    {
        $report = new RiskReport();
        $report->addFileRisk('a.php', 15.0, 3, ['loose_comparison' => 2, 'curly_brace_access' => 1]);

        $this->assertSame(1, $report->getTotalFiles());
        $this->assertSame(3, $report->getTotalIssues());
        $this->assertSame(15.0, $report->getTotalRiskScore());
    }

    public function testGetTopRisks(): void
    {
        $report = new RiskReport();
        $report->addFileRisk('low.php', 5.0, 1, []);
        $report->addFileRisk('high.php', 50.0, 10, []);
        $report->addFileRisk('mid.php', 20.0, 4, []);

        $top = $report->getTopRisks(2);

        $this->assertCount(2, $top);
        $this->assertSame('high.php', $top[0]->filePath);
        $this->assertSame('mid.php', $top[1]->filePath);
    }

    public function testGetSafestFirst(): void
    {
        $report = new RiskReport();
        $report->addFileRisk('high.php', 50.0, 10, []);
        $report->addFileRisk('low.php', 5.0, 1, []);

        $safest = $report->getSafestFirst();

        $this->assertSame('low.php', $safest[0]->filePath);
        $this->assertSame('high.php', $safest[1]->filePath);
    }

    public function testGetCategorySummary(): void
    {
        $report = new RiskReport();
        $report->addFileRisk('a.php', 10.0, 3, ['loose_comparison' => 2, 'curly_brace_access' => 1]);
        $report->addFileRisk('b.php', 5.0, 2, ['loose_comparison' => 1, 'dynamic_property' => 1]);

        $summary = $report->getCategorySummary();

        $this->assertSame(3, $summary['loose_comparison']);
        $this->assertSame(1, $summary['curly_brace_access']);
        $this->assertSame(1, $summary['dynamic_property']);
    }

    public function testGetFileRisk(): void
    {
        $report = new RiskReport();
        $report->addFileRisk('a.php', 10.0, 3, []);

        $this->assertInstanceOf(FileRisk::class, $report->getFileRisk('a.php'));
        $this->assertNull($report->getFileRisk('nonexistent.php'));
    }

    public function testToArray(): void
    {
        $report = new RiskReport();
        $report->addFileRisk('a.php', 10.0, 3, ['x' => 1]);

        $arr = $report->toArray();

        $this->assertSame(1, $arr['total_files']);
        $this->assertSame(3, $arr['total_issues']);
        $this->assertSame(10.0, $arr['total_risk_score']);
        $this->assertArrayHasKey('categories', $arr);
        $this->assertArrayHasKey('files', $arr);
    }
}
