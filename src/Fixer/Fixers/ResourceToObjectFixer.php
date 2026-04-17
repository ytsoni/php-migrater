<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer\Fixers;

use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Fixer\FixerInterface;

/**
 * Replaces is_resource($x) with ($x instanceof \CurlHandle || ...) checks
 * for resources migrated to objects in PHP 8.0+.
 */
final class ResourceToObjectFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'resource_to_object';
    }

    public function getDescription(): string
    {
        return 'Replace is_resource() with instanceof checks for migrated resource types';
    }

    public function getPriority(): int
    {
        return 40;
    }

    public function supports(Issue $issue): bool
    {
        return $issue->category === IssueCategory::ResourceToObject;
    }

    public function fix(string $sourceCode, array $issues): string
    {
        if (empty($issues)) {
            return $sourceCode;
        }

        // Simple regex-based replacement: is_resource($var) -> ($var instanceof \CurlHandle || is_resource($var))
        // This ensures backward compatibility while fixing forward compatibility
        $sourceCode = preg_replace_callback(
            '/\bis_resource\s*\(\s*(\$\w+)\s*\)/',
            function (array $matches): string {
                $var = $matches[1];
                return "({$var} instanceof \\CurlHandle || {$var} instanceof \\GdImage || is_resource({$var}))";
            },
            $sourceCode,
        ) ?? $sourceCode;

        return $sourceCode;
    }
}
