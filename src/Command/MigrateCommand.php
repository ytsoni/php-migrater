<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Diff\DiffGenerator;
use Ylab\PhpMigrater\Fixer\IncrementalMigrator;
use Ylab\PhpMigrater\Fixer\RectorAdapter;
use Ylab\PhpMigrater\Plugin\EventDispatcher;
use Ylab\PhpMigrater\Plugin\PluginRegistry;
use Ylab\PhpMigrater\Reporter\ConsoleReporter;
use Ylab\PhpMigrater\Reporter\MigrationReport;
use Ylab\PhpMigrater\TestGenerator\TestWriter;

#[AsCommand(name: 'migrate', description: 'Run a full migration: analyze, generate tests, fix, and report')]
class MigrateCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', 'php-migrater.php')
            ->addOption('batch', null, InputOption::VALUE_NONE, 'Apply all fixes without interactive prompts')
            ->addOption('browser-diff', null, InputOption::VALUE_NONE, 'Show diffs in browser')
            ->addOption('skip-tests', null, InputOption::VALUE_NONE, 'Skip test generation')
            ->addOption('skip-rector', null, InputOption::VALUE_NONE, 'Skip Rector processing')
            ->addOption('report-format', null, InputOption::VALUE_REQUIRED, 'Report format: console, json, html', 'console')
            ->addOption('report-file', null, InputOption::VALUE_REQUIRED, 'Save report to file');
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
        $registry = new PluginRegistry($config);

        $report = new MigrationReport($config->getSourceVersion(), $config->getTargetVersion());

        // Phase 1: Analyze
        $output->writeln('<info>Phase 1: Analyzing codebase...</info>');
        $analyzers = $registry->getAnalyzers();
        $finder = $config->createFinder();
        $allFiles = iterator_to_array($finder);
        $files = [];
        $issuesByFile = [];
        $filesAnalyzed = 0;

        $output->writeln(sprintf('Found <info>%d</info> file(s) to analyze.', count($allFiles)));
        $output->writeln('');

        $progressBar = new ProgressBar($output, count($allFiles));
        $progressBar->setFormat(
            " %current%/%max% [%bar%] %percent:3s%% %elapsed:8s% / ~%estimated:-8s% remaining\n"
            . " %memory:6s% | Issues: %issues% | File: %filename%\n"
            . " Operation: %operation%"
        );
        $progressBar->setMessage('0', 'issues');
        $progressBar->setMessage('Starting...', 'filename');
        $progressBar->setMessage('Initializing', 'operation');
        $progressBar->start();

        $totalIssueCount = 0;

        foreach ($allFiles as $file) {
            $filesAnalyzed++;
            $filePath = $file->getRealPath() ?: $file->getPathname();
            $files[] = $file;

            $progressBar->setMessage($file->getRelativePathname(), 'filename');

            $allIssues = [];
            foreach ($analyzers as $analyzer) {
                $progressBar->setMessage($analyzer->getName(), 'operation');
                $progressBar->display();
                $allIssues = array_merge($allIssues, $analyzer->analyze($file, $config));
            }

            if (!empty($allIssues)) {
                $issuesByFile[$filePath] = $allIssues;
                $report->addFileIssues($filePath, $allIssues);
            }

            $totalIssueCount += count($allIssues);
            $progressBar->setMessage((string) $totalIssueCount, 'issues');
            $progressBar->advance();
        }

        $progressBar->setMessage('Done', 'operation');
        $progressBar->setMessage('Complete', 'filename');
        $progressBar->finish();
        $output->writeln('');
        $output->writeln('');

        $report->setFilesAnalyzed($filesAnalyzed);
        $totalIssues = array_sum(array_map('count', $issuesByFile));
        $output->writeln(sprintf('  Found %d issues in %d files out of %d analyzed.', $totalIssues, count($issuesByFile), $filesAnalyzed));

        // Phase 2: Generate tests
        if (!$input->getOption('skip-tests')) {
            $output->writeln('');
            $output->writeln('<info>Phase 2: Generating tests...</info>');
            $generators = $registry->getTestGenerators();
            $writer = new TestWriter();
            $testsGenerated = 0;

            $progressBar2 = new ProgressBar($output, count($files));
            $progressBar2->setFormat(
                " %current%/%max% [%bar%] %percent:3s%% %elapsed:8s% / ~%estimated:-8s% remaining\n"
                . " Tests: %tests% | File: %filename%\n"
                . " Generator: %operation%"
            );
            $progressBar2->setMessage('0', 'tests');
            $progressBar2->setMessage('Starting...', 'filename');
            $progressBar2->setMessage('Initializing', 'operation');
            $progressBar2->start();

            foreach ($files as $file) {
                $sourceCode = file_get_contents($file->getRealPath() ?: $file->getPathname());
                if ($sourceCode === false) {
                    $progressBar2->advance();
                    continue;
                }

                $progressBar2->setMessage($file->getRelativePathname(), 'filename');

                foreach ($generators as $generator) {
                    $progressBar2->setMessage($generator->getName(), 'operation');
                    $progressBar2->display();

                    $tests = $generator->generate($sourceCode, $file->getRealPath() ?: $file->getPathname());
                    $written = $writer->write($tests, $output);
                    $testsGenerated += count($written);
                }

                $progressBar2->setMessage((string) $testsGenerated, 'tests');
                $progressBar2->advance();
            }

            $progressBar2->setMessage('Done', 'operation');
            $progressBar2->setMessage('Complete', 'filename');
            $progressBar2->finish();
            $output->writeln('');
            $output->writeln('');

            $report->setTestsGenerated($testsGenerated);
            $output->writeln(sprintf('  Generated %d test files.', $testsGenerated));
        }

        // Phase 3: Rector
        if (!$input->getOption('skip-rector')) {
            $rector = new RectorAdapter();
            if ($rector->isAvailable()) {
                $output->writeln('');
                $output->writeln('<info>Phase 3: Running Rector...</info>');
                foreach ($config->getPaths() as $path) {
                    $rectorOutput = $rector->apply($path, $config);
                    if ($output->isVerbose() && $rectorOutput !== '') {
                        $output->writeln($rectorOutput);
                    }
                }
                $output->writeln('  Rector processing complete.');
            } else {
                $output->writeln('');
                $output->writeln('<comment>Phase 3: Rector not available, skipping.</comment>');
            }
        }

        // Phase 4: Apply custom fixes
        if ($totalIssues > 0) {
            $output->writeln('');
            $output->writeln('<info>Phase 4: Applying fixes...</info>');

            $fixerRegistry = $registry->getFixerRegistry();
            $diffGen = new DiffGenerator();
            $migrator = new IncrementalMigrator($fixerRegistry, $diffGen, $dispatcher, $config);

            $interactive = !$input->getOption('batch');
            $browserDiff = (bool) $input->getOption('browser-diff');

            $result = $migrator->migrate($files, $issuesByFile, $output, $interactive, $browserDiff);
            $report->setFilesFixed($result->applied);

            $output->writeln(sprintf('  Applied: %d | Skipped: %d | Failed: %d', $result->applied, $result->skipped, $result->failed));
        }

        // Phase 5: Report
        $report->finish();
        $output->writeln('');
        $output->writeln('<info>Phase 5: Report</info>');

        $format = $input->getOption('report-format');
        $reporter = match ($format) {
            'json' => new \Ylab\PhpMigrater\Reporter\JsonReporter(),
            'html' => new \Ylab\PhpMigrater\Reporter\HtmlReporter(),
            default => new ConsoleReporter(),
        };

        $rendered = $reporter->render($report);
        $reportFile = $input->getOption('report-file');

        if ($reportFile !== null) {
            $dir = dirname($reportFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($reportFile, $rendered);
            $output->writeln(sprintf('Report saved to %s', $reportFile));
        } else {
            $output->writeln($rendered);
        }

        return Command::SUCCESS;
    }
}
