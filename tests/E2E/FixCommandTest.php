<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Ylab\PhpMigrater\Command\FixCommand;

final class FixCommandTest extends TestCase
{
    private CommandTester $tester;
    private string $tmpDir;

    protected function setUp(): void
    {
        $app = new Application('php-migrater', '0.1.0');
        $app->add(new FixCommand());
        $command = $app->find('fix');
        $this->tester = new CommandTester($command);

        // Create a temporary copy of the fixture project so we don't mutate the originals
        $this->tmpDir = sys_get_temp_dir() . '/php-migrater-e2e-fix-' . uniqid();
        mkdir($this->tmpDir . '/src', 0755, true);

        $fixtureDir = __DIR__ . '/../Fixtures/e2e_project';
        foreach (glob($fixtureDir . '/src/*.php') as $file) {
            copy($file, $this->tmpDir . '/src/' . basename($file));
        }

        // Create config pointing to the tmp dir
        $config = "<?php\nreturn [\n    'source' => '5.6',\n    'target' => '8.4',\n    'paths' => ['" . str_replace('\\', '/', $this->tmpDir) . "/src'],\n    'exclude' => [],\n    'state_file' => '" . str_replace('\\', '/', $this->tmpDir) . "/.php-migrater-state.json',\n];\n";
        file_put_contents($this->tmpDir . '/php-migrater.php', $config);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testDryRunShowsDiffsWithoutModifyingFiles(): void
    {
        $originalContent = file_get_contents($this->tmpDir . '/src/UserService.php');

        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--dry-run' => true,
        ]);

        $output = $this->tester->getDisplay();

        // Should show analysis output
        $this->assertStringContainsString('Analyzing files before fixing', $output);

        // Should find issues
        $this->assertStringContainsString('Found', $output);
        $this->assertStringContainsString('issues', $output);

        // Original files should be unmodified
        $this->assertSame($originalContent, file_get_contents($this->tmpDir . '/src/UserService.php'));

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testBatchModeAppliesAllFixes(): void
    {
        $originalContent = file_get_contents($this->tmpDir . '/src/UserService.php');

        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
        ]);

        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('Applying all fixes in batch mode', $output);
        $this->assertStringContainsString('Applied:', $output);

        // Files with fixes should be modified
        $fixedContent = file_get_contents($this->tmpDir . '/src/UserService.php');
        $this->assertNotSame($originalContent, $fixedContent);

        // Loose comparisons should be fixed
        $this->assertStringContainsString('===', $fixedContent);
    }

    public function testBatchModeFixesImplicitNullable(): void
    {
        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
        ]);

        $fixedHelpers = file_get_contents($this->tmpDir . '/src/helpers.php');

        // Implicit nullable should be fixed
        $this->assertStringContainsString('?string', $fixedHelpers);
    }

    public function testFixWithMissingConfigFails(): void
    {
        $this->tester->execute([
            '--config' => '/nonexistent/config.php',
        ]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Config file not found', $output);
        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testResetStateOption(): void
    {
        // First run creates state
        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
        ]);

        // State file should exist
        $stateFile = $this->tmpDir . '/.php-migrater-state.json';
        $this->assertFileExists($stateFile);

        // Reset and re-run
        $this->tester->execute([
            '--config' => $this->tmpDir . '/php-migrater.php',
            '--batch' => true,
            '--reset-state' => true,
        ]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('Migration state reset', $output);
    }

    public function testCleanProjectHasNoFixes(): void
    {
        // Create a tmp dir with only the clean file
        $cleanDir = sys_get_temp_dir() . '/php-migrater-e2e-clean-' . uniqid();
        mkdir($cleanDir . '/src', 0755, true);
        copy(__DIR__ . '/../Fixtures/e2e_project/src/CleanService.php', $cleanDir . '/src/CleanService.php');

        $config = "<?php\nreturn [\n    'source' => '8.1',\n    'target' => '8.4',\n    'paths' => ['" . str_replace('\\', '/', $cleanDir) . "/src'],\n    'exclude' => [],\n    'state_file' => '" . str_replace('\\', '/', $cleanDir) . "/.state.json',\n];\n";
        file_put_contents($cleanDir . '/php-migrater.php', $config);

        $this->tester->execute([
            '--config' => $cleanDir . '/php-migrater.php',
            '--batch' => true,
        ]);

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('No issues found', $output);
        $this->assertSame(0, $this->tester->getStatusCode());

        $this->removeDir($cleanDir);
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
