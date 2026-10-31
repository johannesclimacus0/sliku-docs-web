<?php

declare(strict_types=1);

namespace App\Broadcast\Commands;

use App\Broadcast\Publisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckBroadcastCommand extends Command
{
    public function __construct(private Publisher $publisher)
    {
        parent::__construct('broadcast:check');
    }

    protected function configure(): void
    {
        $this->setDescription('Check Centrifugo HTTP API connectivity and authentication');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->publisher->check();
            $output->writeln('<info>Centrifugo API is reachable. Nodes: ' . count($result['nodes'] ?? []) . '</info>');
            $output->writeln('Browser WebSocket access and origins must be checked separately.');
            return Command::SUCCESS;
        } catch (\Throwable $error) {
            $output->writeln('<error>Centrifugo check failed (' . get_debug_type($error) . '). Check BROADCAST_ENABLED, API URL, key and server logs.</error>');
            return Command::FAILURE;
        }
    }
}
