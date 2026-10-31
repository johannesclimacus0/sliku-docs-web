<?php

declare(strict_types=1);

namespace App\Webhooks\Commands;

use App\Queue\Queue;
use App\Webhooks\Jobs\DeliverWebhook;
use App\Webhooks\WebhookStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RetryWebhookCommand extends Command
{
    public function __construct(private WebhookStore $store, private Queue $queue)
    {
        parent::__construct('webhooks:retry');
    }

    protected function configure(): void
    {
        $this->setDescription('Queue another attempt of an undelivered webhook')
            ->addArgument('id', InputArgument::REQUIRED, 'Delivery ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');
        $delivery = $this->store->find($id);
        if ($delivery === null) {
            $io->error('Webhook delivery not found.');
            return Command::FAILURE;
        }
        if ($delivery['status'] === 'delivered') {
            $io->success('Already delivered; no new job queued.');
            return Command::SUCCESS;
        }
        $this->queue->dispatch(new DeliverWebhook($id));
        $io->success('Webhook queued again with the same delivery ID.');
        return Command::SUCCESS;
    }
}
