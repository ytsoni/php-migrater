<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\RiskScorer;
use Ylab\PhpMigrater\Analyzer\Severity;

final class RiskScorerTest extends TestCase
{
    private RiskScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new RiskScorer();
    }

    public function testEmptyIssuesProduceEmptyReport(): void
    {
        $report = $this->scorer->score([]);

        $this->assertSame(0, $report->getTotalFiles());
        $this->assertSame(0, $report->getTotalIssues());
        $this->assertSame(0.0, $report->getTotalRiskScore());
    }

    public function testScoringWithMockIssues(): void
    {
        $issues = [
            'file1.php' => [
                new Issue('file1.php', 10, 0, Severity::Error, 'Err', IssueCategory::LooseComparison),
                new Issue('file1.php', 20, 0, Severity::Warning, 'Warn', IssueCategory::CurlyBraceAccess),
            ],
            'file2.php' => [
                new Issue('file2.php', 5, 0, Severity::Info, 'Info', IssueCategory::Other),
            ],
        ];

        $report = $this->scorer->score($issues);

        $this->assertSame(2, $report->getTotalFiles());
        $this->assertSame(3, $report->getTotalIssues());
        $this->assertGreaterThan(0.0, $report->getTotalRiskScore());
    }

    public function testHigherSeverityProducesHigherScore(): void
    {
        $errorIssues = [
            'error.php' => [
                new Issue('error.php', 1, 0, Severity::Error, 'Err', IssueCategory::LooseComparison),
            ],
        ];
        $infoIssues = [
            'info.php' => [
                new Issue('info.php', 1, 0, Severity::Info, 'Info', IssueCategory::Other),
            ],
        ];

        $errorReport = $this->scorer->score($errorIssues);
        $infoReport = $this->scorer->score($infoIssues);

        // Error weight (10) > Info weight (1), so error file should score higher
        $this->assertGreaterThan(
            $infoReport->getTotalRiskScore(),
            $errorReport->getTotalRiskScore(),
        );
    }
}
