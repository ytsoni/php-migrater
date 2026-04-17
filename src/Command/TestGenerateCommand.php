<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Plugin\EventDispatcher;
use Ylab\PhpMigrater\Plugin\PluginRegistry;
use Ylab\PhpMigrater\TestGenerator\TestWriter;

#[AsCommand(name: 'test:generate', description: 'Generate characterization and behavioral tests for source files')]
class TestGenerateCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', 'php-migrater.php')
            ->addOption('output-dir', 'o', InputOption::VALUE_REQUIRED, 'Output directory for generated tests', 'tests/generated');
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

        $generators = $registry->getTestGenerators();
        $finder = $config->createFinder();
        $writer = new TestWriter();

        $output->writeln('<info>Generating tests...</info>');

        $totalGenerated = 0;

        foreach ($finder as $file) {
            $sourceCode = file_get_contents($file->getRealPath() ?: $file->getPathname());
            if ($sourceCode === false) {
                continue;
            }

            $filePath = $file->getRealPath() ?: $file->getPathname();

            foreach ($generators as $generator) {
                $tests = $generator->generate($sourceCode, $filePath);
                $written = $writer->write($tests, $output);
                $totalGenerated += count($written);
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Generated %d test files.</info>', $totalGenerated));

        return Command::SUCCESS;
    }
}
