<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use Ylab\PhpMigrater\Config\PhpVersion;

final class VersionProfile
{
    /** @var array<string, PhpVersion> feature name => minimum PHP version required */
    private array $features = [];

    private ?PhpVersion $detectedMinimum = null;

    public function addFeature(string $feature, PhpVersion $minimumVersion): void
    {
        $this->features[$feature] = $minimumVersion;
        $this->detectedMinimum = null; // invalidate cache
    }

    public function getDetectedMinimumVersion(): PhpVersion
    {
        if ($this->detectedMinimum !== null) {
            return $this->detectedMinimum;
        }

        $min = PhpVersion::PHP_53;
        foreach ($this->features as $version) {
            if ($min->isOlderThan($version)) {
                $min = $version;
            }
        }

        return $this->detectedMinimum = $min;
    }

    /** @return array<string, PhpVersion> */
    public function getFeatures(): array
    {
        return $this->features;
    }

    public function hasFeature(string $feature): bool
    {
        return isset($this->features[$feature]);
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->features as $name => $version) {
            $result[$name] = $version->value;
        }
        return $result;
    }
}
