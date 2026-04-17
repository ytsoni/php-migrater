<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Ylab\PhpMigrater\Command\MigrateCommand;

final class MigrateCommandTest extends TestCase
{
    private CommandTester $tester;
    private string $tmpDir;

    protected function setUp(): void
    {
        $app = new Application('php-migrater', '0.1.0');
        $app->add(new MigrateCommand());
        $command = $app->find('migrate');
        $this->tester = new CommandTester($command);

        // Create a temp workspace
        $this->tmpDir = sys_get_temp_dir() . '/php-migrater-e2e-migrate-' . uniqid();
        mkdir($this->tmpDir . '/src', 0755, true);

        $fixtureDir = __DIR__ . '/../Fixtures/e2e_project';
        foreach (glob($fixtureDir . '/src/*.php') as $file) {
            copy($file, $this->tmpDir . '/src/' . basename($file));
        }

        $config = "<?php\nreturn [\n    'source' => '5.6',\n    'target' => '8.4',\n    'paths' => ['" . str_replace('\\', '/', $this->tmpDir) . "/src'],\n    'exclude' => [],\n    'test_output' => '" . str_replace('\\', '/', $this->tmpDir) . "/tests/generated',\n    'report_output' => '" . str_replace('\\', '/', $this->tmpDir) . "/report',\n    'state_file' => '" . str_replace('\\', '/', $this->tmpDir) . "/.php-migrater-state.json',\n];\n";
        file_put_contents($this->tmpDir . '/php-migrater.php', $config);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testFullMigrationPipelineInBatchMode(): void
    {
        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
            '--skip-rector' => true,
            '--skip-tests' => true,
        ]);

        $output = $this->tester->getDisplay();

        // Should run through all phases
        $this->assertStringContainsString('Phase 1: Analyzing', $output);
        $this->assertStringContainsString('Phase 4: Applying fixes', $output);
        $this->assertStringContainsString('Phase 5: Report', $output);
        $this->assertStringContainsString('Applied:', $output);

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testAllPhasesRunWithTestGeneration(): void
    {
        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
            '--skip-rector' => true,
        ]);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('Phase 1:', $output);
        $this->assertStringContainsString('Phase 2: Generating tests', $output);
        $this->assertStringContainsString('Phase 4:', $output);
        $this->assertStringContainsString('Phase 5:', $output);

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testRectorSkipMessageWhenUnavailable(): void
    {
        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
            '--skip-tests' => true,
        ]);

        $output = $this->tester->getDisplay();

        // Rector is not installed, should show "not available"
        $this->assertStringContainsString('Rector not available', $output);
    }

    public function testReportSavedToFile(): void
    {
        $reportFile = $this->tmpDir . '/report/migration-report.json';

        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
            '--skip-rector' => true,
            '--skip-tests' => true,
            '--report-format' => 'json',
            '--report-file' => $reportFile,
        ]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Report saved to', $output);
        $this->assertFileExists($reportFile);

        // Report should be valid JSON
        $reportContent = file_get_contents($reportFile);
        $this->assertNotFalse($reportContent);
        $decoded = json_decode($reportContent, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('summary', $decoded);
    }

    public function testMigrateWithMissingConfigFails(): void
    {
        $this->tester->execute([
            '--config' => '/nonexistent/config.php',
        ]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Config file not found', $output);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testMigrateFixesActualCode(): void
    {
        $originalContent = file_get_contents($this->tmpDir . '/src/UserService.php');

        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
            '--skip-rector' => true,
            '--skip-tests' => true,
        ]);

        $fixedContent = file_get_contents($this->tmpDir . '/src/UserService.php');

        // Should have applied fixes
        $this->assertNotSame($originalContent, $fixedContent);
        $this->assertStringContainsString('===', $fixedContent);
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
