<?php

namespace Blackbird\IndexMonitoring\Console\Command;

use Blackbird\IndexMonitoring\Logger\Logger;
use Blackbird\IndexMonitoring\Service\MonitorService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckIndexerStatus extends Command
{

    public function __construct(
        private readonly MonitorService $monitorService,
        private readonly Logger $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure(): void
    {
        $this->setName('blackbird:indexer:checkstatus');
        $this->setDescription('Use this command to verify indexer status and notify admins');
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->monitorService->execute();
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
