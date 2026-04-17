<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Config;

enum PhpVersion: string
{
    case PHP_53 = '5.3';
    case PHP_54 = '5.4';
    case PHP_55 = '5.5';
    case PHP_56 = '5.6';
    case PHP_70 = '7.0';
    case PHP_71 = '7.1';
    case PHP_72 = '7.2';
    case PHP_73 = '7.3';
    case PHP_74 = '7.4';
    case PHP_80 = '8.0';
    case PHP_81 = '8.1';
    case PHP_82 = '8.2';
    case PHP_83 = '8.3';
    case PHP_84 = '8.4';

    public function isOlderThan(self $other): bool
    {
        return version_compare($this->value, $other->value, '<');
    }

    public function isNewerThanOrEqual(self $other): bool
    {
        return version_compare($this->value, $other->value, '>=');
    }

    public function majorMinor(): string
    {
        return $this->value;
    }

    public static function fromString(string $version): self
    {
        $normalized = match (true) {
            str_starts_with($version, '5.3') => '5.3',
            str_starts_with($version, '5.4') => '5.4',
            str_starts_with($version, '5.5') => '5.5',
            str_starts_with($version, '5.6') => '5.6',
            str_starts_with($version, '7.0') => '7.0',
            str_starts_with($version, '7.1') => '7.1',
            str_starts_with($version, '7.2') => '7.2',
            str_starts_with($version, '7.3') => '7.3',
            str_starts_with($version, '7.4') => '7.4',
            str_starts_with($version, '8.0') => '8.0',
            str_starts_with($version, '8.1') => '8.1',
            str_starts_with($version, '8.2') => '8.2',
            str_starts_with($version, '8.3') => '8.3',
            str_starts_with($version, '8.4') => '8.4',
            default => throw new \InvalidArgumentException("Unsupported PHP version: {$version}"),
        };

        return self::from($normalized);
    }

    /** @return self[] All versions between $from and $to (inclusive) */
    public static function range(self $from, self $to): array
    {
        $versions = [];
        $inRange = false;

        foreach (self::cases() as $version) {
            if ($version === $from) {
                $inRange = true;
            }
            if ($inRange) {
                $versions[] = $version;
            }
            if ($version === $to) {
                break;
            }
        }

        return $versions;
    }
}
