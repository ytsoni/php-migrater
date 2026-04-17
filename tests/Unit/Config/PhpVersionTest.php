<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Config\PhpVersion;

final class PhpVersionTest extends TestCase
{
    public function testFromStringBasic(): void
    {
        $this->assertSame(PhpVersion::PHP_56, PhpVersion::fromString('5.6'));
        $this->assertSame(PhpVersion::PHP_81, PhpVersion::fromString('8.1'));
        $this->assertSame(PhpVersion::PHP_74, PhpVersion::fromString('7.4.33'));
    }

    public function testFromStringThrowsOnInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhpVersion::fromString('9.0');
    }

    public function testIsOlderThan(): void
    {
        $this->assertTrue(PhpVersion::PHP_56->isOlderThan(PhpVersion::PHP_74));
        $this->assertFalse(PhpVersion::PHP_81->isOlderThan(PhpVersion::PHP_74));
        $this->assertFalse(PhpVersion::PHP_74->isOlderThan(PhpVersion::PHP_74));
    }

    public function testIsNewerThanOrEqual(): void
    {
        $this->assertTrue(PhpVersion::PHP_81->isNewerThanOrEqual(PhpVersion::PHP_81));
        $this->assertTrue(PhpVersion::PHP_82->isNewerThanOrEqual(PhpVersion::PHP_81));
        $this->assertFalse(PhpVersion::PHP_56->isNewerThanOrEqual(PhpVersion::PHP_74));
    }

    public function testRange(): void
    {
        $range = PhpVersion::range(PhpVersion::PHP_74, PhpVersion::PHP_81);

        $this->assertCount(3, $range);
        $this->assertSame(PhpVersion::PHP_74, $range[0]);
        $this->assertSame(PhpVersion::PHP_80, $range[1]);
        $this->assertSame(PhpVersion::PHP_81, $range[2]);
    }

    public function testRangeSingleVersion(): void
    {
        $range = PhpVersion::range(PhpVersion::PHP_80, PhpVersion::PHP_80);
        $this->assertCount(1, $range);
        $this->assertSame(PhpVersion::PHP_80, $range[0]);
    }

    public function testMajorMinor(): void
    {
        $this->assertSame('8.1', PhpVersion::PHP_81->majorMinor());
    }
}
