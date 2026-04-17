<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer;

use PhpParser\Node;
use Ylab\PhpMigrater\Analyzer\Issue;

interface FixerInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Higher priority fixers run first.
     */
    public function getPriority(): int;

    /**
     * Whether this fixer can handle the given issue.
     */
    public function supports(Issue $issue): bool;

    /**
     * Apply the fix to source code and return the modified source.
     *
     * @param string $sourceCode Original PHP source code
     * @param Issue[] $issues Issues this fixer should address in the file
     * @return string Modified PHP source code
     */
    public function fix(string $sourceCode, array $issues): string;
}
