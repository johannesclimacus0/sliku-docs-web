<?php

declare(strict_types=1);

namespace App\Webhooks\Commands;

use App\Webhooks\WebhookStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WebhookDeliveriesCommand extends Command
{
    public function __construct(private WebhookStore $store)
    {
        parent::__construct('webhooks:deliveries');
    }

    protected function configure(): void
    {
        $this->setDescription('Show recent webhook deliveries or attempts for one delivery')
            ->addArgument('id', InputArgument::OPTIONAL, 'Delivery ID to inspect');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');
        if ($id === null) {
            $rows = $this->store->recent();
            $io->table(['Delivery ID', 'Event', 'Endpoint', 'Status', 'Attempts', 'HTTP', 'Created UTC'], array_map(
                static fn (array $row): array => [$row['id'], $row['event_name'], $row['endpoint'], $row['status'],
                    $row['attempts'], $row['last_http_status'] ?? '-', gmdate('Y-m-d H:i:s', (int) $row['created_at'])],
                $rows,
            ));
            return Command::SUCCESS;
        }
        $delivery = $this->store->find($id);
        if ($delivery === null) {
            $io->error('Webhook delivery not found.');
            return Command::FAILURE;
        }
        $io->definitionList(['ID' => $id], ['Event' => $delivery['event_name']], ['Endpoint' => $delivery['endpoint']], ['Status' => $delivery['status']]);
        $io->table(['Attempt', 'Time UTC', 'HTTP', 'Error', 'Duration ms'], array_map(
            static fn (array $row): array => [$row['attempt_no'], gmdate('Y-m-d H:i:s', (int) $row['attempted_at']),
                $row['http_status'] ?? '-', $row['error'] ?? '-', $row['duration_ms']],
            $this->store->attempts($id),
        ));
        return Command::SUCCESS;
    }
}
