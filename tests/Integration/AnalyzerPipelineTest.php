<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use Ylab\PhpMigrater\Analyzer\AstIssueDetector;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Config\PhpVersion;

final class AnalyzerPipelineTest extends TestCase
{
    private Configuration $config;

    protected function setUp(): void
    {
        $this->config = Configuration::fromDefaults(
            source: PhpVersion::PHP_56,
            target: PhpVersion::PHP_84,
        );
    }

    public function testAstDetectorFindsIssuesInLegacyFixture(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/php56/legacy_code.php';
        $this->assertFileExists($fixturePath);

        $detector = new AstIssueDetector();
        $file = new SplFileInfo($fixturePath, '', 'legacy_code.php');
        $issues = $detector->analyze($file, $this->config);

        $this->assertNotEmpty($issues, 'Should detect issues in legacy PHP 5.6 code');

        // Collect categories found
        $categories = array_map(fn($i) => $i->category, $issues);

        // Should detect at least loose comparisons and implicit nullable
        $this->assertContains(IssueCategory::LooseComparison, $categories);
        $this->assertContains(IssueCategory::ImplicitNullable, $categories);
    }

    public function testAstDetectorFindsIssuesInPhp74Fixture(): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/php74/migration_targets.php';
        $this->assertFileExists($fixturePath);

        $detector = new AstIssueDetector();
        $file = new SplFileInfo($fixturePath, '', 'migration_targets.php');

        $config = Configuration::fromDefaults(
            source: PhpVersion::PHP_74,
            target: PhpVersion::PHP_84,
        );

        $issues = $detector->analyze($file, $config);

        $this->assertNotEmpty($issues, 'Should detect issues in PHP 7.4 code targeting 8.4');

        $categories = array_map(fn($i) => $i->category, $issues);

        // Should detect implicit nullable and dynamic property
        $this->assertContains(IssueCategory::ImplicitNullable, $categories);
        $this->assertContains(IssueCategory::DynamicProperty, $categories);
    }
}
