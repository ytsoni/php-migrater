<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

final class RiskReport
{
    /** @var array<string, FileRisk> Keyed by file path */
    private array $fileRisks = [];

    public function addFileRisk(string $filePath, float $riskScore, int $issueCount, array $categories): void
    {
        $this->fileRisks[$filePath] = new FileRisk($filePath, $riskScore, $issueCount, $categories);
    }

    /** @return FileRisk[] Sorted by risk score descending */
    public function getTopRisks(int $limit = 10): array
    {
        $risks = array_values($this->fileRisks);
        usort($risks, fn(FileRisk $a, FileRisk $b) => $b->riskScore <=> $a->riskScore);
        return array_slice($risks, 0, $limit);
    }

    /** @return FileRisk[] Sorted by risk score ascending (safest first) */
    public function getSafestFirst(): array
    {
        $risks = array_values($this->fileRisks);
        usort($risks, fn(FileRisk $a, FileRisk $b) => $a->riskScore <=> $b->riskScore);
        return $risks;
    }

    public function getTotalRiskScore(): float
    {
        return array_sum(array_map(fn(FileRisk $r) => $r->riskScore, $this->fileRisks));
    }

    public function getTotalFiles(): int
    {
        return count($this->fileRisks);
    }

    public function getTotalIssues(): int
    {
        return array_sum(array_map(fn(FileRisk $r) => $r->issueCount, $this->fileRisks));
    }

    public function getFileRisk(string $filePath): ?FileRisk
    {
        return $this->fileRisks[$filePath] ?? null;
    }

    /** @return array<string, int> Category => count */
    public function getCategorySummary(): array
    {
        $summary = [];
        foreach ($this->fileRisks as $risk) {
            foreach ($risk->categories as $category => $count) {
                $summary[$category] = ($summary[$category] ?? 0) + $count;
            }
        }
        arsort($summary);
        return $summary;
    }

    public function toArray(): array
    {
        return [
            'total_files' => $this->getTotalFiles(),
            'total_issues' => $this->getTotalIssues(),
            'total_risk_score' => $this->getTotalRiskScore(),
            'categories' => $this->getCategorySummary(),
            'files' => array_map(fn(FileRisk $r) => $r->toArray(), array_values($this->fileRisks)),
        ];
    }
}
