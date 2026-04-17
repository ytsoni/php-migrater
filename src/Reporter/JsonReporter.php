<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Reporter;

final class JsonReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'json';
    }

    public function render(MigrationReport $report): string
    {
        $data = [
            'migration' => [
                'source' => $report->getSourceVersion()->value,
                'target' => $report->getTargetVersion()->value,
            ],
            'summary' => [
                'filesAnalyzed' => $report->getFilesAnalyzed(),
                'filesWithIssues' => $report->getFilesWithIssues(),
                'totalIssues' => $report->getTotalIssueCount(),
                'filesFixed' => $report->getFilesFixed(),
                'testsGenerated' => $report->getTestsGenerated(),
                'duration' => round($report->getDuration(), 3),
            ],
            'severities' => $report->getIssueSeverityCounts(),
            'categories' => $report->getIssueCategoryCounts(),
            'files' => [],
        ];

        foreach ($report->getIssuesByFile() as $file => $issues) {
            if (empty($issues)) {
                continue;
            }
            $data['files'][$file] = array_map(
                fn($issue) => $issue->toArray(),
                $issues,
            );
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
