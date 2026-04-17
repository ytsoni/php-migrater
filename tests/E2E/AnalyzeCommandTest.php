<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Ylab\PhpMigrater\Command\AnalyzeCommand;

final class AnalyzeCommandTest extends TestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $app = new Application('php-migrater', '0.1.0');
        $app->add(new AnalyzeCommand());
        $command = $app->find('analyze');
        $this->tester = new CommandTester($command);
    }

    public function testAnalyzeFindsIssuesInFixtureProject(): void
    {
        $configPath = __DIR__ . '/../Fixtures/e2e_project/php-migrater.php';
        $this->assertFileExists($configPath);

        $this->tester->execute([
            '--config' => $configPath,
        ]);

        $output = $this->tester->getDisplay();

        // Should detect issues
        $this->assertStringContainsString('Analyzing codebase', $output);
        $this->assertStringContainsString('Migration Analysis Report', $output);
        $this->assertStringContainsString('Total issues:', $output);

        // Should find more than 0 issues (we have loose comparisons, implicit nullable, etc.)
        $this->assertDoesNotMatchRegularExpression('/Total issues:\s+0/', $output);

        // Exit code should be FAILURE (issues found)
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testAnalyzeShowsVersionInfo(): void
    {
        $configPath = __DIR__ . '/../Fixtures/e2e_project/php-migrater.php';

        $this->tester->execute([
            '--config' => $configPath,
        ]);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('PHP 5.6', $output);
        $this->assertStringContainsString('PHP 8.4', $output);
    }

    public function testAnalyzeWithVerboseShowsPerFileStatus(): void
    {
        $configPath = __DIR__ . '/../Fixtures/e2e_project/php-migrater.php';

        $this->tester->execute(
            ['--config' => $configPath],
            ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE],
        );

        $output = $this->tester->getDisplay();

        // Verbose output should mention individual files
        $this->assertStringContainsString('UserService.php', $output);
        $this->assertStringContainsString('helpers.php', $output);
    }

    public function testAnalyzeWithMissingConfigFails(): void
    {
        $this->tester->execute([
            '--config' => '/nonexistent/config.php',
        ]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Config file not found', $output);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testAnalyzeReportShowsSeverityBreakdown(): void
    {
        $configPath = __DIR__ . '/../Fixtures/e2e_project/php-migrater.php';

        $this->tester->execute([
            '--config' => $configPath,
        ]);

        $output = $this->tester->getDisplay();

        // Report should contain severity sections
        $this->assertStringContainsString('Issues by Severity', $output);
        $this->assertStringContainsString('Warnings:', $output);
    }

    public function testAnalyzeReportShowsCategoryBreakdown(): void
    {
        $configPath = __DIR__ . '/../Fixtures/e2e_project/php-migrater.php';

        $this->tester->execute([
            '--config' => $configPath,
        ]);

        $output = $this->tester->getDisplay();

        // Should detect specific category types from our fixtures
        $this->assertStringContainsString('Issues by Category', $output);
    }
}
