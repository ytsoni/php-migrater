<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Reporter;

use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\Severity;

final class ConsoleReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'console';
    }

    public function render(MigrationReport $report): string
    {
        $lines = [];
        $lines[] = '';
        $lines[] = '╔══════════════════════════════════════════════════╗';
        $lines[] = '║          PHP Migration Analysis Report           ║';
        $lines[] = '╚══════════════════════════════════════════════════╝';
        $lines[] = '';

        $lines[] = sprintf(
            'Migration: PHP %s → PHP %s',
            $report->getSourceVersion()->value,
            $report->getTargetVersion()->value,
        );
        $lines[] = sprintf('Duration: %.2f seconds', $report->getDuration());
        $lines[] = '';

        // Summary stats
        $lines[] = '── Summary ──────────────────────────────────────';
        $lines[] = sprintf('  Files analyzed:    %d', $report->getFilesAnalyzed());
        $lines[] = sprintf('  Files with issues: %d', $report->getFilesWithIssues());
        $lines[] = sprintf('  Total issues:      %d', $report->getTotalIssueCount());
        $lines[] = sprintf('  Files fixed:       %d', $report->getFilesFixed());
        $lines[] = sprintf('  Tests generated:   %d', $report->getTestsGenerated());
        $lines[] = '';

        // Severity breakdown
        $severities = $report->getIssueSeverityCounts();
        $lines[] = '── Issues by Severity ───────────────────────────';
        $lines[] = sprintf('  Errors:   %d', $severities['error'] ?? 0);
        $lines[] = sprintf('  Warnings: %d', $severities['warning'] ?? 0);
        $lines[] = sprintf('  Info:     %d', $severities['info'] ?? 0);
        $lines[] = '';

        // Category breakdown
        $categories = $report->getIssueCategoryCounts();
        if (!empty($categories)) {
            $lines[] = '── Issues by Category ───────────────────────────';
            foreach ($categories as $category => $count) {
                $lines[] = sprintf('  %-30s %d', $category, $count);
            }
            $lines[] = '';
        }

        // Per-file details (top 20)
        $issuesByFile = $report->getIssuesByFile();
        if (!empty($issuesByFile)) {
            $lines[] = '── File Details ─────────────────────────────────';

            $fileCount = 0;
            foreach ($issuesByFile as $file => $issues) {
                if (empty($issues)) {
                    continue;
                }
                if (++$fileCount > 20) {
                    $remaining = count($issuesByFile) - 20;
                    $lines[] = sprintf('  ... and %d more files', $remaining);
                    break;
                }

                $lines[] = '';
                $lines[] = sprintf('  %s (%d issues)', $file, count($issues));

                foreach ($issues as $issue) {
                    $icon = match ($issue->severity) {
                        Severity::Error => '✗',
                        Severity::Warning => '⚠',
                        Severity::Info => 'ℹ',
                    };
                    $lines[] = sprintf(
                        '    %s Line %d: %s [%s]',
                        $icon,
                        $issue->line,
                        $issue->message,
                        $issue->category->value,
                    );
                }
            }
            $lines[] = '';
        }

        // Risk report
        $riskReport = $report->getRiskReport();
        if ($riskReport !== null) {
            $topRisks = $riskReport->getTopRisks(10);
            if (!empty($topRisks)) {
                $lines[] = '── Highest Risk Files ───────────────────────────';
                foreach ($topRisks as $risk) {
                    $lines[] = sprintf(
                        '  %.1f  %s (%d issues)',
                        $risk->riskScore,
                        $risk->filePath,
                        $risk->issueCount,
                    );
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
