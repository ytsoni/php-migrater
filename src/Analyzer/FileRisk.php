<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

final readonly class FileRisk
{
    public function __construct(
        public string $filePath,
        public float $riskScore,
        public int $issueCount,
        /** @var array<string, int> category => count */
        public array $categories,
    ) {}

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
