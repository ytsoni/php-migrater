<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Config\PhpVersion;

final class ConfigurationTest extends TestCase
{
    public function testFromDefaults(): void
    {
        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_81, ['src']);

        $this->assertSame(PhpVersion::PHP_56, $config->getSourceVersion());
        $this->assertSame(PhpVersion::PHP_81, $config->getTargetVersion());
        $this->assertSame(['src'], $config->getPaths());
        $this->assertSame(1, $config->getParallelWorkers());
    }

    public function testSourceMustBeOlderThanTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Configuration::fromDefaults(PhpVersion::PHP_81, PhpVersion::PHP_56);
    }

    public function testSameVersionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Configuration::fromDefaults(PhpVersion::PHP_81, PhpVersion::PHP_81);
    }

    public function testVersionRange(): void
    {
        $config = Configuration::fromDefaults(PhpVersion::PHP_74, PhpVersion::PHP_82);
        $range = $config->getVersionRange();

        $this->assertSame(PhpVersion::PHP_74, $range[0]);
        $this->assertSame(PhpVersion::PHP_82, end($range));
    }

    public function testLoadFromFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/php-migrater-test-' . uniqid() . '.php';
        file_put_contents($tmpFile, "<?php\nreturn ['source' => '5.6', 'target' => '8.1', 'paths' => ['src'], 'parallel' => 4];");

        try {
            $config = Configuration::load($tmpFile);

            $this->assertSame(PhpVersion::PHP_56, $config->getSourceVersion());
            $this->assertSame(PhpVersion::PHP_81, $config->getTargetVersion());
            $this->assertSame(['src'], $config->getPaths());
            $this->assertSame(4, $config->getParallelWorkers());
        } finally {
            unlink($tmpFile);
        }
    }

    public function testLoadFromFileMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        Configuration::load('/nonexistent/file.php');
    }

    public function testGettersReturnDefaults(): void
    {
        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_81);

        $this->assertSame('report', $config->getReportOutputDir());
        $this->assertSame('tests/migration', $config->getTestOutputDir());
        $this->assertSame('.php-migrater-state.json', $config->getStateFile());
        $this->assertSame([], $config->getExcludes());
        $this->assertSame([], $config->getPlugins());
    }
}
