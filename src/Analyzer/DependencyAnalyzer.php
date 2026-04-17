<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use SplFileInfo;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Config\PhpVersion;

/**
 * Analyzes composer.json/composer.lock to find packages incompatible with the target PHP version.
 */
final class DependencyAnalyzer implements AnalyzerInterface
{
    public function getName(): string
    {
        return 'dependency_analyzer';
    }

    public function analyze(SplFileInfo $file, Configuration $config): array
    {
        // This analyzer works on composer.lock, not individual PHP files
        return [];
    }

    /**
     * Analyze the project's composer dependencies for PHP version compatibility.
     *
     * @return Issue[]
     */
    public function analyzeDependencies(string $projectRoot, Configuration $config): array
    {
        $lockFile = $projectRoot . '/composer.lock';
        if (!file_exists($lockFile)) {
            return [
                new Issue(
                    file: $projectRoot . '/composer.lock',
                    line: 0,
                    column: 0,
                    severity: Severity::Info,
                    message: 'No composer.lock found. Cannot analyze dependency compatibility.',
                    category: IssueCategory::Dependency,
                ),
            ];
        }

        $lockContent = file_get_contents($lockFile);
        if ($lockContent === false) {
            return [];
        }
        $lockData = json_decode($lockContent, true);
        if (!is_array($lockData)) {
            return [];
        }

        $targetVersion = $config->getTargetVersion();
        $issues = [];
        $packages = array_merge($lockData['packages'] ?? [], $lockData['packages-dev'] ?? []);

        foreach ($packages as $package) {
            $name = $package['name'] ?? 'unknown';
            $phpRequirement = $package['require']['php'] ?? null;

            if ($phpRequirement === null) {
                continue;
            }

            if (!$this->versionSatisfiesConstraint($targetVersion, $phpRequirement)) {
                $issues[] = new Issue(
                    file: $lockFile,
                    line: 0,
                    column: 0,
                    severity: Severity::Error,
                    message: "Package {$name} requires PHP {$phpRequirement}, which may be incompatible with target PHP {$targetVersion->value}.",
                    category: IssueCategory::Dependency,
                );
            }
        }

        return $issues;
    }

    private function versionSatisfiesConstraint(PhpVersion $target, string $constraint): bool
    {
        // Basic constraint parsing — handles common patterns
        $constraint = trim($constraint);
        $targetNum = $target->value;

        // Handle simple constraints: >=7.0, ^7.0, ~7.0, >=7.0 <8.0
        $parts = preg_split('/\s*\|\|\s*/', $constraint);
        if (!is_array($parts)) {
            return false;
        }
        foreach ($parts as $part) {
            if ($this->satisfiesSingleConstraint($targetNum, trim($part))) {
                return true;
            }
        }

        return false;
    }

    private function satisfiesSingleConstraint(string $target, string $constraint): bool
    {
        // Handle compound constraints: >=7.0 <9.0
        $subParts = preg_split('/\s+/', $constraint);
        if (is_array($subParts) && count($subParts) > 1) {
            foreach ($subParts as $sub) {
                if (!$this->satisfiesSingleConstraint($target, trim($sub))) {
                    return false;
                }
            }
            return true;
        }

        // Caret: ^7.4 means >=7.4.0 <8.0.0
        if (str_starts_with($constraint, '^')) {
            $version = substr($constraint, 1);
            $parts = explode('.', $version);
            $major = (int) ($parts[0] ?? 0);
            return version_compare($target, $version, '>=')
                && version_compare($target, ($major + 1) . '.0', '<');
        }

        // Tilde: ~7.4 means >=7.4.0 <8.0.0
        if (str_starts_with($constraint, '~')) {
            $version = substr($constraint, 1);
            $parts = explode('.', $version);
            $major = (int) ($parts[0] ?? 0);
            return version_compare($target, $version, '>=')
                && version_compare($target, ($major + 1) . '.0', '<');
        }

        // Comparison operators
        if (str_starts_with($constraint, '>=')) {
            return version_compare($target, substr($constraint, 2), '>=');
        }
        if (str_starts_with($constraint, '<=')) {
            return version_compare($target, substr($constraint, 2), '<=');
        }
        if (str_starts_with($constraint, '>')) {
            return version_compare($target, substr($constraint, 1), '>');
        }
        if (str_starts_with($constraint, '<')) {
            return version_compare($target, substr($constraint, 1), '<');
        }
        if (str_starts_with($constraint, '!=')) {
            return version_compare($target, substr($constraint, 2), '!=');
        }

        // Wildcard: 7.* or 7.4.*
        if (str_contains($constraint, '*')) {
            $prefix = rtrim($constraint, '.*');
            return str_starts_with($target, $prefix);
        }

        // Exact match
        return version_compare($target, $constraint, '>=');
    }
}
