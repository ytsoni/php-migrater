<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Fixer\MigrationResult;

final class MigrationResultTest extends TestCase
{
    public function testTotalProcessed(): void
    {
        $result = new MigrationResult(applied: 5, skipped: 2, failed: 1, aborted: false);

        $this->assertSame(8, $result->totalProcessed());
    }

    public function testIsSuccessfulWhenNoFailures(): void
    {
        $result = new MigrationResult(applied: 5, skipped: 2, failed: 0, aborted: false);

        $this->assertTrue($result->isSuccessful());
    }

    public function testNotSuccessfulWithFailures(): void
    {
        $result = new MigrationResult(applied: 5, skipped: 0, failed: 1, aborted: false);

        $this->assertFalse($result->isSuccessful());
    }

    public function testNotSuccessfulWhenAborted(): void
    {
        $result = new MigrationResult(applied: 0, skipped: 0, failed: 0, aborted: true);

        $this->assertFalse($result->isSuccessful());
    }

    public function testProperties(): void
    {
        $result = new MigrationResult(applied: 3, skipped: 1, failed: 2, aborted: false);

        $this->assertSame(3, $result->applied);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(2, $result->failed);
        $this->assertFalse($result->aborted);
    }
}
