<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\WebGui\Server;

#[AsCommand(name: 'serve', description: 'Start the web GUI dashboard')]
class ServeCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file', 'php-migrater.php')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to bind', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Port number', '8484');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $input->getOption('config');
        if (!file_exists($configFile)) {
            $output->writeln("<error>Config file not found: {$configFile}</error>");
            return Command::FAILURE;
        }

        $config = Configuration::load($configFile);
        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');

        $output->writeln(sprintf('<info>Starting PHP Migrater web dashboard on http://%s:%d</info>', $host, $port));
        $output->writeln('Press Ctrl+C to stop.');

        $server = new Server($config, $host, $port);
        $server->start($output);

        return Command::SUCCESS;
    }
}
