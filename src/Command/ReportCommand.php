<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Plugin\EventDispatcher;
use Ylab\PhpMigrater\Plugin\PluginRegistry;
use Ylab\PhpMigrater\Reporter\ConsoleReporter;
use Ylab\PhpMigrater\Reporter\HtmlReporter;
use Ylab\PhpMigrater\Reporter\JsonReporter;
use Ylab\PhpMigrater\Reporter\MigrationReport;

#[AsCommand(name: 'report', description: 'Generate a migration report in various formats')]
class ReportCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', 'php-migrater.php')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Report format: console, json, html', 'console')
            ->addArgument('output-file', InputArgument::OPTIONAL, 'Output file path (defaults to stdout)');
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

        $report = new MigrationReport($config->getSourceVersion(), $config->getTargetVersion());
        $filesAnalyzed = 0;

        $output->writeln('<info>Analyzing for report...</info>');

        foreach ($finder as $file) {
            $filesAnalyzed++;
            $allIssues = [];

            foreach ($analyzers as $analyzer) {
                $allIssues = array_merge($allIssues, $analyzer->analyze($file, $config));
            }

            if (!empty($allIssues)) {
                $report->addFileIssues($file->getRealPath() ?: $file->getPathname(), $allIssues);
            }
        }

        $report->setFilesAnalyzed($filesAnalyzed);
        $report->finish();

        $format = $input->getOption('format');
        $reporter = match ($format) {
            'json' => new JsonReporter(),
            'html' => new HtmlReporter(),
            default => new ConsoleReporter(),
        };

        $rendered = $reporter->render($report);

        $outputFile = $input->getArgument('output-file');
        if ($outputFile !== null) {
            $dir = dirname($outputFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($outputFile, $rendered);
            $output->writeln(sprintf('<info>Report written to %s</info>', $outputFile));
        } else {
            $output->writeln($rendered);
        }

        return Command::SUCCESS;
    }
}
