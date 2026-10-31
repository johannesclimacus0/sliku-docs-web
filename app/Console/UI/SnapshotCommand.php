<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SnapshotCommand extends Command
{
    public function __construct(private Snapshot $snapshot)
    {
        parent::__construct('ui:snapshot');
    }

    protected function configure(): void
    {
        $this->setHidden(true)->setDescription('Return local terminal dashboard data as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(json_encode($this->snapshot->read(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        return Command::SUCCESS;
    }
}
