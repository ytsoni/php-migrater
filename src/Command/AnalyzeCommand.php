<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ylab\PhpMigrater\Analyzer\AstIssueDetector;
use Ylab\PhpMigrater\Analyzer\CompatibilityScanner;
use Ylab\PhpMigrater\Analyzer\DependencyAnalyzer;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\RiskScorer;
use Ylab\PhpMigrater\Analyzer\VersionDetector;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Plugin\EventDispatcher;
use Ylab\PhpMigrater\Plugin\PluginRegistry;
use Ylab\PhpMigrater\Reporter\ConsoleReporter;
use Ylab\PhpMigrater\Reporter\MigrationReport;

#[AsCommand(name: 'analyze', description: 'Analyze PHP codebase for migration issues')]
class AnalyzeCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', 'php-migrater.php')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (console, json)', 'console')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Override source path to analyze');
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

        $analyzers = $registry->getAnalyzers();
        $finder = $config->createFinder();

        $overridePath = $input->getOption('path');
        if ($overridePath !== null) {
            $finder = $config->createFinder([$overridePath]);
        }

        $report = new MigrationReport($config->getSourceVersion(), $config->getTargetVersion());

        $output->writeln('<info>Analyzing codebase...</info>');
        $output->writeln(sprintf(
            'Migration target: PHP %s → PHP %s',
            $config->getSourceVersion()->value,
            $config->getTargetVersion()->value,
        ));
        $output->writeln('');

        // Collect files upfront so we know the total count for progress/ETA
        $files = iterator_to_array($finder);
        $totalFiles = count($files);
        $filesAnalyzed = 0;
        $totalIssues = 0;

        $output->writeln(sprintf('Found <info>%d</info> file(s) to analyze.', $totalFiles));
        $output->writeln('');

        $progressBar = new ProgressBar($output, $totalFiles);
        $progressBar->setFormat(
            " %current%/%max% [%bar%] %percent:3s%% %elapsed:8s% / ~%estimated:-8s% remaining\n"
            . " %memory:6s% | Issues: %issues% | File: %filename%\n"
            . " Operation: %operation%"
        );
        $progressBar->setMessage('0', 'issues');
        $progressBar->setMessage('Starting...', 'filename');
        $progressBar->setMessage('Initializing', 'operation');
        $progressBar->start();

        foreach ($files as $file) {
            $filesAnalyzed++;
            $allIssues = [];
            $relativePath = $file->getRelativePathname();

            $progressBar->setMessage($relativePath, 'filename');

            foreach ($analyzers as $analyzer) {
                $progressBar->setMessage($analyzer->getName(), 'operation');
                $progressBar->display();

                $issues = $analyzer->analyze($file, $config);
                $allIssues = array_merge($allIssues, $issues);
            }

            $totalIssues += count($allIssues);
            $progressBar->setMessage((string) $totalIssues, 'issues');

            if (!empty($allIssues)) {
                $report->addFileIssues($file->getRealPath() ?: $file->getPathname(), $allIssues);
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Done', 'operation');
        $progressBar->setMessage('Complete', 'filename');
        $progressBar->finish();
        $output->writeln('');
        $output->writeln('');

        $report->setFilesAnalyzed($filesAnalyzed);
        $report->finish();

        $reporter = new ConsoleReporter();
        $output->writeln($reporter->render($report));

        return $report->getTotalIssueCount() > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
