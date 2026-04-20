#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Auto-increment the patch version in composer.json and bin/php-migrater.
 *
 * Usage:
 *   php scripts/bump-version.php          # bump patch (0.2.0 -> 0.2.1)
 *   php scripts/bump-version.php minor    # bump minor (0.2.1 -> 0.3.0)
 *   php scripts/bump-version.php major    # bump major (0.3.0 -> 1.0.0)
 */

$rootDir = dirname(__DIR__);
$composerFile = $rootDir . '/composer.json';
$binFile = $rootDir . '/bin/php-migrater';

$bumpType = $argv[1] ?? 'patch';

if (!in_array($bumpType, ['patch', 'minor', 'major'], true)) {
    fwrite(STDERR, "Invalid bump type: {$bumpType}. Use: patch, minor, or major.\n");
    exit(1);
}

// Read current version from composer.json
$composerJson = file_get_contents($composerFile);
if ($composerJson === false) {
    fwrite(STDERR, "Error: Cannot read {$composerFile}\n");
    exit(1);
}

$composer = json_decode($composerJson, true);
if (!is_array($composer) || !isset($composer['version'])) {
    fwrite(STDERR, "Error: No 'version' field found in composer.json\n");
    exit(1);
}

$currentVersion = $composer['version'];
$parts = explode('.', $currentVersion);

if (count($parts) !== 3) {
    fwrite(STDERR, "Error: Version '{$currentVersion}' is not in semver format (X.Y.Z)\n");
    exit(1);
}

[$major, $minor, $patch] = array_map('intval', $parts);

switch ($bumpType) {
    case 'major':
        $major++;
        $minor = 0;
        $patch = 0;
        break;
    case 'minor':
        $minor++;
        $patch = 0;
        break;
    case 'patch':
        $patch++;
        break;
}

$newVersion = "{$major}.{$minor}.{$patch}";

// Update composer.json
$composer['version'] = $newVersion;
$composer['extra']['branch-alias']['dev-main'] = "{$major}.{$minor}-dev";
$newComposerJson = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($composerFile, $newComposerJson);

// Update bin/php-migrater
$binContent = file_get_contents($binFile);
if ($binContent === false) {
    fwrite(STDERR, "Error: Cannot read {$binFile}\n");
    exit(1);
}

$updatedBin = preg_replace(
    "/new Application\('PHP Migrater',\s*'[^']+'\)/",
    "new Application('PHP Migrater', '{$newVersion}')",
    $binContent
);

if ($updatedBin === null || $updatedBin === $binContent && $currentVersion !== $newVersion) {
    fwrite(STDERR, "Warning: Could not update version in bin/php-migrater\n");
} else {
    file_put_contents($binFile, $updatedBin);
}

echo "Version bumped: {$currentVersion} -> {$newVersion}\n";
