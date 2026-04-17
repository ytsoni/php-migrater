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

    public function testScoringWithRealFiles(): void
    {
        // Use an actual PHP file for complexity estimation
        $issues = [
            __FILE__ => [
                new Issue(__FILE__, 1, 0, Severity::Warning, 'Warn', IssueCategory::LooseComparison),
            ],
        ];

        $report = $this->scorer->score($issues);
        $this->assertSame(1, $report->getTotalFiles());
        $this->assertGreaterThan(0.0, $report->getTotalRiskScore());
    }

    public function testComplexFilesGetHigherScore(): void
    {
        $tmpDir = sys_get_temp_dir() . '/php-migrater-risk-' . uniqid();
        mkdir($tmpDir, 0755, true);

        // Simple file
        $simpleFile = $tmpDir . '/simple.php';
        file_put_contents($simpleFile, '<?php echo "hello";');

        // Complex file with many decision points
        $complexCode = '<?php ' . "\n";
        for ($i = 0; $i < 20; $i++) {
            $complexCode .= "if (\$x$i > $i) { for (\$j = 0; \$j < $i; \$j++) { echo \$j; } }\n";
        }
        $complexFile = $tmpDir . '/complex.php';
        file_put_contents($complexFile, $complexCode);

        $simpleIssues = [$simpleFile => [
            new Issue($simpleFile, 1, 0, Severity::Error, 'E', IssueCategory::LooseComparison),
        ]];
        $complexIssues = [$complexFile => [
            new Issue($complexFile, 1, 0, Severity::Error, 'E', IssueCategory::LooseComparison),
        ]];

        $simpleReport = $this->scorer->score($simpleIssues);
        $complexReport = $this->scorer->score($complexIssues);

        // Complex file should have higher risk score
        $this->assertGreaterThanOrEqual(
            $simpleReport->getTotalRiskScore(),
            $complexReport->getTotalRiskScore(),
        );

        // Cleanup
        unlink($simpleFile);
        unlink($complexFile);
        rmdir($tmpDir);
    }

    public function testNonexistentFileDefaultsComplexityTo1(): void
    {
        $issues = [
            '/nonexistent/file.php' => [
                new Issue('/nonexistent/file.php', 1, 0, Severity::Error, 'E', IssueCategory::LooseComparison),
            ],
        ];
        $report = $this->scorer->score($issues);

        // Error weight (10) * default complexity (1.0) = 10.0
        $this->assertEqualsWithDelta(10.0, $report->getTotalRiskScore(), 0.01);
    }

    public function testCategoriesAreTracked(): void
    {
        $issues = [
            'f.php' => [
                new Issue('f.php', 1, 0, Severity::Error, 'E', IssueCategory::LooseComparison),
                new Issue('f.php', 2, 0, Severity::Warning, 'W', IssueCategory::LooseComparison),
                new Issue('f.php', 3, 0, Severity::Info, 'I', IssueCategory::CurlyBraceAccess),
            ],
        ];
        $report = $this->scorer->score($issues);
        $risk = $report->getFileRisk('f.php');

        $this->assertNotNull($risk);
        $this->assertSame(2, $risk->categories['loose_comparison']);
        $this->assertSame(1, $risk->categories['curly_brace_access']);
    }

    public function testReportSortingMethods(): void
    {
        $issues = [
            '/nonexistent/a.php' => [
                new Issue('/nonexistent/a.php', 1, 0, Severity::Info, 'I', IssueCategory::Other),
            ],
            '/nonexistent/b.php' => [
                new Issue('/nonexistent/b.php', 1, 0, Severity::Error, 'E', IssueCategory::LooseComparison),
                new Issue('/nonexistent/b.php', 2, 0, Severity::Error, 'E', IssueCategory::LooseComparison),
            ],
        ];
        $report = $this->scorer->score($issues);

        $topRisks = $report->getTopRisks(1);
        $this->assertCount(1, $topRisks);
        $this->assertStringContainsString('b.php', $topRisks[0]->filePath);

        $safest = $report->getSafestFirst();
        $this->assertStringContainsString('a.php', $safest[0]->filePath);
    }

    public function testReportToArray(): void
    {
        $issues = [
            '/nonexistent/x.php' => [
                new Issue('/nonexistent/x.php', 1, 0, Severity::Warning, 'W', IssueCategory::Other),
            ],
        ];
        $report = $this->scorer->score($issues);
        $data = $report->toArray();

        $this->assertArrayHasKey('total_files', $data);
        $this->assertArrayHasKey('total_issues', $data);
        $this->assertArrayHasKey('total_risk_score', $data);
        $this->assertArrayHasKey('categories', $data);
        $this->assertArrayHasKey('files', $data);
        $this->assertSame(1, $data['total_files']);
    }
}
