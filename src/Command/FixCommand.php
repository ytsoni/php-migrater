<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Diff\DiffGenerator;
use Ylab\PhpMigrater\Fixer\FixerRegistry;
use Ylab\PhpMigrater\Fixer\IncrementalMigrator;
use Ylab\PhpMigrater\Plugin\EventDispatcher;
use Ylab\PhpMigrater\Plugin\PluginRegistry;

#[AsCommand(name: 'fix', description: 'Apply automated fixes to migration issues')]
class FixCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', 'php-migrater.php')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without applying')
            ->addOption('batch', null, InputOption::VALUE_NONE, 'Apply all fixes without interactive prompts')
            ->addOption('browser-diff', null, InputOption::VALUE_NONE, 'Show diffs in browser instead of terminal')
            ->addOption('reset-state', null, InputOption::VALUE_NONE, 'Reset migration state and start fresh');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $input->getOption('config');
        if (!file_exists($configFile)) {
            $output->writeln("<error>Config file not found: {$configFile}</error>");
            return Command::FAILURE;
        }

        $config = Configuration::load($configFile);
        $dispatcher = new EventDispatcher();
        $registry = new PluginRegistry($config, $dispatcher);

        // Analyze first
        $analyzers = $registry->getAnalyzers();
        $finder = $config->createFinder();
        $files = [];
        $issuesByFile = [];

        $output->writeln('<info>Analyzing files before fixing...</info>');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath() ?: $file->getPathname();
            $files[] = $file;

            $fileIssues = [];
            foreach ($analyzers as $analyzer) {
                $fileIssues = array_merge($fileIssues, $analyzer->analyze($file, $config));
            }

            if (!empty($fileIssues)) {
                $issuesByFile[$filePath] = $fileIssues;
            }
        }

        $totalIssues = array_sum(array_map('count', $issuesByFile));
        $output->writeln(sprintf('Found %d issues in %d files.', $totalIssues, count($issuesByFile)));

        if ($totalIssues === 0) {
            $output->writeln('<info>No issues found. Nothing to fix.</info>');
            return Command::SUCCESS;
        }

        // Dry run: just show what fixers would do
        if ($input->getOption('dry-run')) {
            $fixerRegistry = $registry->getFixerRegistry();
            $diffGen = new DiffGenerator();

            foreach ($issuesByFile as $filePath => $issues) {
                $original = file_get_contents($filePath);
                if ($original === false) {
                    continue;
                }

                $fixed = $fixerRegistry->applyFixes($original, $issues);
                if ($fixed !== $original) {
                    $diff = $diffGen->generate($original, $fixed, $filePath);
                    $output->writeln(sprintf("\n<comment>%s</comment>", $filePath));
                    $output->writeln($diff->unifiedDiff);
                }
            }

            return Command::SUCCESS;
        }

        // Apply fixes
        $fixerRegistry = $registry->getFixerRegistry();
        $diffGen = new DiffGenerator();
        $migrator = new IncrementalMigrator($fixerRegistry, $diffGen, $dispatcher, $config);

        if ($input->getOption('reset-state')) {
            $migrator->resetState();
            $output->writeln('<comment>Migration state reset.</comment>');
        }

        $interactive = !$input->getOption('batch');
        $browserDiff = (bool) $input->getOption('browser-diff');

        $output->writeln($interactive ? '<info>Starting interactive migration...</info>' : '<info>Applying all fixes in batch mode...</info>');

        $result = $migrator->migrate($files, $issuesByFile, $output, $interactive, $browserDiff);

        $output->writeln('');
        $output->writeln(sprintf('Applied: %d | Skipped: %d | Failed: %d', $result->applied, $result->skipped, $result->failed));

        if ($result->aborted) {
            $output->writeln('<comment>Migration was aborted. Use --reset-state to start over, or resume from where you left off.</comment>');
        }

        return $result->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
