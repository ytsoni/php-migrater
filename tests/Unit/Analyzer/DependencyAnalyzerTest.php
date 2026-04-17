<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use Ylab\PhpMigrater\Analyzer\DependencyAnalyzer;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Config\PhpVersion;

final class DependencyAnalyzerTest extends TestCase
{
    private DependencyAnalyzer $analyzer;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->analyzer = new DependencyAnalyzer();
        $this->tmpDir = sys_get_temp_dir() . '/php-migrater-dep-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testName(): void
    {
        $this->assertSame('dependency_analyzer', $this->analyzer->getName());
    }

    public function testAnalyzeReturnsEmptyForPhpFiles(): void
    {
        $file = new SplFileInfo(__FILE__, '', basename(__FILE__));
        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_84);
        $this->assertSame([], $this->analyzer->analyze($file, $config));
    }

    public function testMissingComposerLockReturnsInfoIssue(): void
    {
        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertCount(1, $issues);
        $this->assertSame(Severity::Info, $issues[0]->severity);
        $this->assertStringContainsString('No composer.lock found', $issues[0]->message);
    }

    public function testCompatiblePackageProducesNoIssues(): void
    {
        $lock = [
            'packages' => [
                [
                    'name' => 'vendor/compatible',
                    'require' => ['php' => '>=7.0'],
                ],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_74, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    public function testIncompatiblePackageProducesError(): void
    {
        $lock = [
            'packages' => [
                [
                    'name' => 'vendor/old-package',
                    'require' => ['php' => '>=5.3 <7.0'],
                ],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertNotEmpty($issues);
        $this->assertSame(Severity::Error, $issues[0]->severity);
        $this->assertSame(IssueCategory::Dependency, $issues[0]->category);
        $this->assertStringContainsString('vendor/old-package', $issues[0]->message);
    }

    public function testCaretConstraintCompatible(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'vendor/pkg', 'require' => ['php' => '^8.0']],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_74, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    public function testTildeConstraint(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'vendor/pkg', 'require' => ['php' => '~8.1']],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_80, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    public function testOrConstraint(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'vendor/pkg', 'require' => ['php' => '>=7.4 || >=8.0']],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_74, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    public function testPackageWithoutPhpRequirementSkipped(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'vendor/no-php-req', 'require' => ['some/other' => '^1.0']],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    public function testDevPackagesAlsoAnalyzed(): void
    {
        $lock = [
            'packages' => [],
            'packages-dev' => [
                ['name' => 'vendor/dev-old', 'require' => ['php' => '>=5.3 <7.0']],
            ],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('vendor/dev-old', $issues[0]->message);
    }

    public function testInvalidJsonReturnsEmpty(): void
    {
        file_put_contents($this->tmpDir . '/composer.lock', 'not json');

        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    public function testWildcardConstraint(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'vendor/pkg', 'require' => ['php' => '8.*']],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_74, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    public function testCompoundConstraint(): void
    {
        $lock = [
            'packages' => [
                ['name' => 'vendor/pkg', 'require' => ['php' => '>=8.0 <9.0']],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->tmpDir . '/composer.lock', json_encode($lock));

        $config = Configuration::fromDefaults(PhpVersion::PHP_74, PhpVersion::PHP_84);
        $issues = $this->analyzer->analyzeDependencies($this->tmpDir, $config);

        $this->assertEmpty($issues);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
