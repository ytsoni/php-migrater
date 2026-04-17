<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Plugin\CorePlugin;

final class CorePluginTest extends TestCase
{
    private CorePlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new CorePlugin();
    }

    public function testName(): void
    {
        $this->assertSame('core', $this->plugin->getName());
    }

    public function testProvidesAnalyzers(): void
    {
        $analyzers = $this->plugin->getAnalyzers();
        $this->assertNotEmpty($analyzers);
        $this->assertGreaterThanOrEqual(4, count($analyzers));
    }

    public function testProvidesFixers(): void
    {
        $fixers = $this->plugin->getFixers();
        $this->assertNotEmpty($fixers);
        $this->assertGreaterThanOrEqual(7, count($fixers));
    }

    public function testProvidesTestGenerators(): void
    {
        $generators = $this->plugin->getTestGenerators();
        $this->assertNotEmpty($generators);
    }

    public function testProvidesReporters(): void
    {
        $reporters = $this->plugin->getReporters();
        $this->assertNotEmpty($reporters);
        $this->assertGreaterThanOrEqual(3, count($reporters));
    }
}
