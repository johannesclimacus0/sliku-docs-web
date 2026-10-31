<?php

declare(strict_types=1);

namespace App\Queue\Commands;

use App\Queue\Jobs\WriteLog;
use App\Queue\Queue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DispatchLogCommand extends Command
{
    public function __construct(private Queue $queue)
    {
        parent::__construct('queue:dispatch-log');
    }

    protected function configure(): void
    {
        $this->setDescription('Send a WriteLog job to the async queue')
            ->addArgument('message', InputArgument::REQUIRED, 'Message to write from the worker');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->queue->dispatch(new WriteLog($input->getArgument('message')));

        $fih = OutputFormatter::escape('<>< Worker\'s gonna deal with it i guess ><>');
        $output->writeln("<info>Job queued. $fih</info>");

        return Command::SUCCESS;
    }
}
