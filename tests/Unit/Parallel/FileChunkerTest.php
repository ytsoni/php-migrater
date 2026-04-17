<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Parallel;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Parallel\FileChunker;
use Symfony\Component\Finder\SplFileInfo;

final class FileChunkerTest extends TestCase
{
    private FileChunker $chunker;

    protected function setUp(): void
    {
        $this->chunker = new FileChunker();
    }

    public function testSingleWorkerReturnsOneChunk(): void
    {
        $files = $this->makeFakeFiles(10);
        $chunks = $this->chunker->chunk($files, 1);

        $this->assertCount(1, $chunks);
        $this->assertCount(10, $chunks[0]);
    }

    public function testEmptyFilesReturnsOneEmptyChunk(): void
    {
        $chunks = $this->chunker->chunk([], 4);
        $this->assertCount(1, $chunks);
        $this->assertEmpty($chunks[0]);
    }

    public function testMultipleWorkersCreateMultipleChunks(): void
    {
        $files = $this->makeFakeFiles(8);
        $chunks = $this->chunker->chunk($files, 4);

        $this->assertGreaterThan(1, count($chunks));

        // All files accounted for
        $total = array_sum(array_map('count', $chunks));
        $this->assertSame(8, $total);
    }

    /**
     * @return SplFileInfo[]
     */
    private function makeFakeFiles(int $count): array
    {
        $files = [];
        // Use real temp files so getSize() works
        for ($i = 0; $i < $count; $i++) {
            $tmp = sys_get_temp_dir() . '/php-migrater-test-chunk-' . $i . '.php';
            file_put_contents($tmp, str_repeat('x', ($i + 1) * 100));
            $files[] = new SplFileInfo($tmp, '', basename($tmp));
        }
        return $files;
    }
}
