<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Reporter;

use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\RiskReport;
use Ylab\PhpMigrater\Config\PhpVersion;

final class MigrationReport
{
    /** @var array<string, array<Issue>> file => issues */
    private array $issuesByFile = [];
    private ?RiskReport $riskReport = null;
    private int $filesAnalyzed = 0;
    private int $filesFixed = 0;
    private int $testsGenerated = 0;
    private float $startTime;
    private ?float $endTime = null;
    private PhpVersion $sourceVersion;
    private PhpVersion $targetVersion;

    public function __construct(PhpVersion $sourceVersion, PhpVersion $targetVersion)
    {
        $this->sourceVersion = $sourceVersion;
        $this->targetVersion = $targetVersion;
        $this->startTime = microtime(true);
    }

    public function addFileIssues(string $filePath, array $issues): void
    {
        $this->issuesByFile[$filePath] = $issues;
    }

    public function setRiskReport(RiskReport $riskReport): void
    {
        $this->riskReport = $riskReport;
    }

    public function setFilesAnalyzed(int $count): void
    {
        $this->filesAnalyzed = $count;
    }

    public function setFilesFixed(int $count): void
    {
        $this->filesFixed = $count;
    }

    public function setTestsGenerated(int $count): void
    {
        $this->testsGenerated = $count;
    }

    public function finish(): void
    {
        $this->endTime = microtime(true);
    }

    public function getSourceVersion(): PhpVersion
    {
        return $this->sourceVersion;
    }

    public function getTargetVersion(): PhpVersion
    {
        return $this->targetVersion;
    }

    /**
     * @return array<string, array<Issue>>
     */
    public function getIssuesByFile(): array
    {
        return $this->issuesByFile;
    }

    /**
     * @return array<Issue>
     */
    public function getAllIssues(): array
    {
        return array_merge(...array_values($this->issuesByFile));
    }

    public function getTotalIssueCount(): int
    {
        $count = 0;
        foreach ($this->issuesByFile as $issues) {
            $count += count($issues);
        }
        return $count;
    }

    public function getRiskReport(): ?RiskReport
    {
        return $this->riskReport;
    }

    public function getFilesAnalyzed(): int
    {
        return $this->filesAnalyzed;
    }

    public function getFilesFixed(): int
    {
        return $this->filesFixed;
    }

    public function getTestsGenerated(): int
    {
        return $this->testsGenerated;
    }

    public function getDuration(): float
    {
        $end = $this->endTime ?? microtime(true);
        return $end - $this->startTime;
    }

    public function getFilesWithIssues(): int
    {
        return count(array_filter($this->issuesByFile, fn($issues) => !empty($issues)));
    }

    /**
     * @return array<string, int>
     */
    public function getIssueSeverityCounts(): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0];
        foreach ($this->getAllIssues() as $issue) {
            $counts[$issue->severity->value] = ($counts[$issue->severity->value] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function getIssueCategoryCounts(): array
    {
        $counts = [];
        foreach ($this->getAllIssues() as $issue) {
            $cat = $issue->category->value;
            $counts[$cat] = ($counts[$cat] ?? 0) + 1;
        }
        arsort($counts);
        return $counts;
    }
}
