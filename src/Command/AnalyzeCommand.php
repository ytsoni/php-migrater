<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
        $registry = new PluginRegistry($config, $dispatcher);

        $analyzers = $registry->getAnalyzers();
        $finder = $config->createFinder();

        $overridePath = $input->getOption('path');
        if ($overridePath !== null) {
            $finder = $config->createFinder([$overridePath]);
        }

        $report = new MigrationReport($config->getSourceVersion(), $config->getTargetVersion());
        $filesAnalyzed = 0;

        $output->writeln('<info>Analyzing codebase...</info>');
        $output->writeln(sprintf(
            'Migration target: PHP %s → PHP %s',
            $config->getSourceVersion()->value,
            $config->getTargetVersion()->value,
        ));
        $output->writeln('');

        foreach ($finder as $file) {
            $filesAnalyzed++;
            $allIssues = [];

            foreach ($analyzers as $analyzer) {
                $issues = $analyzer->analyze($file, $config);
                $allIssues = array_merge($allIssues, $issues);
            }

            if (!empty($allIssues)) {
                $report->addFileIssues($file->getRealPath() ?: $file->getPathname(), $allIssues);
            }

            if ($output->isVerbose()) {
                $count = count($allIssues);
                $status = $count > 0 ? "<comment>{$count} issues</comment>" : '<info>OK</info>';
                $output->writeln("  {$file->getRelativePathname()} ... {$status}");
            }
        }

        $report->setFilesAnalyzed($filesAnalyzed);
        $report->finish();

        $reporter = new ConsoleReporter();
        $output->writeln($reporter->render($report));

        return $report->getTotalIssueCount() > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
