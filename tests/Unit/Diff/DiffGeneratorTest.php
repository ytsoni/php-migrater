<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Diff;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Diff\DiffGenerator;

final class DiffGeneratorTest extends TestCase
{
    private DiffGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new DiffGenerator();
    }

    public function testIdenticalStringsProduceNoChanges(): void
    {
        $code = "<?php\necho 'hello';\n";
        $result = $this->generator->generate($code, $code, 'test.php');

        $this->assertFalse($result->hasChanges);
        $this->assertSame('test.php', $result->fileName);
    }

    public function testDifferentStringsProduceChanges(): void
    {
        $original = "<?php\n\$a == \$b;\n";
        $modified = "<?php\n\$a === \$b;\n";

        $result = $this->generator->generate($original, $modified, 'file.php');

        $this->assertTrue($result->hasChanges);
        $this->assertStringContainsString('===', $result->unifiedDiff);
        $this->assertGreaterThan(0, $result->getAddedLineCount());
        $this->assertGreaterThan(0, $result->getRemovedLineCount());
    }

    public function testToArray(): void
    {
        $original = "line1\n";
        $modified = "line2\n";

        $result = $this->generator->generate($original, $modified, 'f.php');
        $arr = $result->toArray();

        $this->assertSame('f.php', $arr['file']);
        $this->assertTrue($arr['has_changes']);
        $this->assertArrayHasKey('diff', $arr);
        $this->assertArrayHasKey('added_lines', $arr);
        $this->assertArrayHasKey('removed_lines', $arr);
    }
}
