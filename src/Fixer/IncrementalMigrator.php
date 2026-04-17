<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Fixer;

use Ylab\PhpMigrater\Analyzer\AnalyzerInterface;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Diff\DiffGenerator;
use Ylab\PhpMigrater\Diff\TerminalRenderer;
use Ylab\PhpMigrater\Diff\FixAction;
use Ylab\PhpMigrater\Diff\BrowserRenderer;
use Ylab\PhpMigrater\Plugin\Event;
use Ylab\PhpMigrater\Plugin\EventDispatcher;
use Ylab\PhpMigrater\Plugin\EventType;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * File-by-file migration with interactive diff preview, rollback, and resume support.
 */
final class IncrementalMigrator
{
    private const STATE_FILE = '.php-migrater-state.json';

    /** @var array<string, string> filePath => 'done'|'skipped' */
    private array $state = [];
    private string $stateFile;
    private bool $applyAll = false;

    public function __construct(
        private readonly FixerRegistry $fixerRegistry,
        private readonly DiffGenerator $diffGenerator,
        private readonly EventDispatcher $eventDispatcher,
        private readonly Configuration $config,
    ) {
        $this->stateFile = $this->config->getStateFile() ?? self::STATE_FILE;
        $this->loadState();
    }

    /**
     * @param array<\SplFileInfo> $files
     * @param array<string, array<Issue>> $issuesByFile filename => issues
     */
    public function migrate(
        array $files,
        array $issuesByFile,
        OutputInterface $output,
        bool $interactive = true,
        bool $browserDiff = false,
    ): MigrationResult {
        $applied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $file) {
            $filePath = $file->getRealPath() ?: $file->getPathname();

            // Skip already-processed files (resume support)
            if (isset($this->state[$filePath])) {
                if ($this->state[$filePath] === 'done') {
                    $applied++;
                } else {
                    $skipped++;
                }
                continue;
            }

            $issues = $issuesByFile[$filePath] ?? [];
            if (empty($issues)) {
                $this->markState($filePath, 'done');
                continue;
            }

            $this->eventDispatcher->dispatch(new Event(
                EventType::BeforeFileFix,
                ['file' => $filePath, 'issueCount' => count($issues)],
            ));

            $originalCode = file_get_contents($filePath);
            if ($originalCode === false) {
                $failed++;
                continue;
            }

            $fixedCode = $this->fixerRegistry->applyFixes($originalCode, $issues);

            if ($fixedCode === $originalCode) {
                $this->markState($filePath, 'done');
                continue;
            }

            $action = FixAction::Apply;

            if ($interactive && !$this->applyAll) {
                $diff = $this->diffGenerator->generate($originalCode, $fixedCode, $filePath);

                if ($browserDiff) {
                    $renderer = new BrowserRenderer();
                    $action = $renderer->showAndWait($diff, $filePath);
                } else {
                    $renderer = new TerminalRenderer($output);
                    $action = $renderer->renderInteractive($diff, $filePath);
                }
            }

            switch ($action) {
                case FixAction::Apply:
                    if ($this->applyFix($filePath, $originalCode, $fixedCode)) {
                        $applied++;
                        $this->markState($filePath, 'done');
                    } else {
                        $failed++;
                    }
                    break;

                case FixAction::ApplyAll:
                    $this->applyAll = true;
                    if ($this->applyFix($filePath, $originalCode, $fixedCode)) {
                        $applied++;
                        $this->markState($filePath, 'done');
                    } else {
                        $failed++;
                    }
                    break;

                case FixAction::Skip:
                    $skipped++;
                    $this->markState($filePath, 'skipped');
                    break;

                case FixAction::Quit:
                    $this->saveState();
                    return new MigrationResult($applied, $skipped, $failed, aborted: true);
            }

            $this->eventDispatcher->dispatch(new Event(
                EventType::AfterFileFix,
                ['file' => $filePath, 'action' => $action->name],
            ));
        }

        $this->saveState();
        return new MigrationResult($applied, $skipped, $failed, aborted: false);
    }

    private function applyFix(string $filePath, string $originalCode, string $fixedCode): bool
    {
        // Create backup
        $backupPath = $filePath . '.bak';
        if (!copy($filePath, $backupPath)) {
            return false;
        }

        if (file_put_contents($filePath, $fixedCode) === false) {
            // Rollback
            copy($backupPath, $filePath);
            unlink($backupPath);
            return false;
        }

        // Remove backup on success
        unlink($backupPath);
        return true;
    }

    public function resetState(): void
    {
        $this->state = [];
        if (file_exists($this->stateFile)) {
            unlink($this->stateFile);
        }
    }

    public function getProcessedCount(): int
    {
        return count($this->state);
    }

    private function markState(string $filePath, string $status): void
    {
        $this->state[$filePath] = $status;
    }

    private function loadState(): void
    {
        if (file_exists($this->stateFile)) {
            $content = file_get_contents($this->stateFile);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->state = $data;
                }
            }
        }
    }

    private function saveState(): void
    {
        file_put_contents(
            $this->stateFile,
            json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
