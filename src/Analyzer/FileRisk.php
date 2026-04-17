<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

final class FileRisk
{
    public function __construct(
        public readonly string $filePath,
        public readonly float $riskScore,
        public readonly int $issueCount,
        /** @var array<string, int> category => count */
        public readonly array $categories,
    ) {}

    /** @return array{file: string, risk_score: float, issue_count: int, categories: array<string, int>} */
    public function toArray(): array
    {
        return [
            'file' => $this->filePath,
            'risk_score' => $this->riskScore,
            'issue_count' => $this->issueCount,
            'categories' => $this->categories,
        ];
    }
}
