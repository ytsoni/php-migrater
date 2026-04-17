<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer;

use Ylab\PhpMigrater\Analyzer\Issue;

/**
 * Collects fixers from plugins, sorts by priority, and matches fixers to issues.
 */
final class FixerRegistry
{
    /** @var FixerInterface[] */
    private array $fixers = [];

    private bool $sorted = false;

    public function register(FixerInterface $fixer): void
    {
        $this->fixers[] = $fixer;
        $this->sorted = false;
    }

    /**
     * Find all fixers that can handle the given issue.
     *
     * @return FixerInterface[]
     */
    public function findFixers(Issue $issue): array
    {
        $this->sort();

        return array_filter(
            $this->fixers,
            fn(FixerInterface $fixer) => $fixer->supports($issue),
        );
    }

    /**
     * Find the best (highest priority) fixer for an issue.
     */
    public function findBestFixer(Issue $issue): ?FixerInterface
    {
        $this->sort();

        foreach ($this->fixers as $fixer) {
            if ($fixer->supports($issue)) {
                return $fixer;
            }
        }

        return null;
    }

    /**
     * Apply all matching fixers to source code for the given issues.
     *
     * @param Issue[] $issues
     */
    public function applyFixes(string $sourceCode, array $issues): string
    {
        $this->sort();

        // Group issues by fixer
        $issuesByFixer = [];
        foreach ($issues as $issue) {
            $fixer = $this->findBestFixer($issue);
            if ($fixer !== null) {
                $key = spl_object_id($fixer);
                $issuesByFixer[$key] ??= ['fixer' => $fixer, 'issues' => []];
                $issuesByFixer[$key]['issues'][] = $issue;
            }
        }

        // Apply fixers in priority order
        foreach ($issuesByFixer as $entry) {
            $sourceCode = $entry['fixer']->fix($sourceCode, $entry['issues']);
        }

        return $sourceCode;
    }

    /** @return FixerInterface[] */
    public function getAll(): array
    {
        $this->sort();
        return $this->fixers;
    }

    private function sort(): void
    {
        if ($this->sorted) {
            return;
        }
        usort($this->fixers, fn(FixerInterface $a, FixerInterface $b) => $b->getPriority() - $a->getPriority());
        $this->sorted = true;
    }
}
