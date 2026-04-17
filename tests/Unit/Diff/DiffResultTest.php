<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Diff;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Diff\DiffResult;

final class DiffResultTest extends TestCase
{
    public function testProperties(): void
    {
        $result = new DiffResult(
            fileName: 'test.php',
            original: "line1\nline2",
            modified: "line1\nchanged",
            unifiedDiff: "--- original\n+++ modified\n@@ @@\n line1\n-line2\n+changed\n",
            hasChanges: true,
        );

        $this->assertSame('test.php', $result->fileName);
        $this->assertSame("line1\nline2", $result->original);
        $this->assertSame("line1\nchanged", $result->modified);
        $this->assertTrue($result->hasChanges);
    }

    public function testAddedLineCount(): void
    {
        $diff = "--- original\n+++ modified\n@@ @@\n line1\n-old\n+new1\n+new2\n";
        $result = new DiffResult('test.php', '', '', $diff, true);

        $this->assertSame(2, $result->getAddedLineCount());
    }

    public function testRemovedLineCount(): void
    {
        $diff = "--- original\n+++ modified\n@@ @@\n line1\n-old1\n-old2\n+new\n";
        $result = new DiffResult('test.php', '', '', $diff, true);

        $this->assertSame(2, $result->getRemovedLineCount());
    }

    public function testNoChanges(): void
    {
        $result = new DiffResult('test.php', 'same', 'same', '', false);

        $this->assertFalse($result->hasChanges);
        $this->assertSame(0, $result->getAddedLineCount());
        $this->assertSame(0, $result->getRemovedLineCount());
    }

    public function testToArray(): void
    {
        $result = new DiffResult('test.php', 'a', 'b', '+b', true);
        $arr = $result->toArray();

        $this->assertSame('test.php', $arr['file']);
        $this->assertTrue($arr['has_changes']);
        $this->assertArrayHasKey('added_lines', $arr);
        $this->assertArrayHasKey('removed_lines', $arr);
        $this->assertArrayHasKey('diff', $arr);
    }
}
