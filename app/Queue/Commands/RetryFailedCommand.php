<?php

declare(strict_types=1);

namespace App\Queue\Commands;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;

class RetryFailedCommand extends Command
{
    public function __construct(
        private DoctrineTransport $async,
        private DoctrineTransport $failed,
        private EntityManagerInterface $em,
    ) {
        parent::__construct('queue:retry');
    }

    protected function configure(): void
    {
        $this->setAliases(['messenger:failed:retry'])
            ->setDescription('Move one failed job back to async with a fresh retry count')
            ->addArgument('id', InputArgument::REQUIRED, 'Failed message ID from queue:failed')
            ->addOption('transport', null, InputOption::VALUE_REQUIRED, 'Failure transport', 'failed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('transport') !== 'failed') {
            throw new InvalidArgumentException('The available failure transport is "failed"');
        }

        $envelope = $this->failed->find($input->getArgument('id'));

        if ($envelope === null) {
            throw new InvalidArgumentException('Failed message not found');
        }

        $this->em->getConnection()->transactional(function () use ($envelope): void {
            $this->async->send(new Envelope($envelope->getMessage()));
            $this->failed->ack($envelope);
        });

        $fih = OutputFormatter::escape('<>< Worker\'s gonna deal with it i guess ><>');
        $output->writeln("<info>Job moved to async. $fih</info>");

        return Command::SUCCESS;
    }
}
