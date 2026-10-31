<?php

declare(strict_types=1);

namespace App\Modules\Auth\Commands;

use App\Modules\Auth\Repositories\RefreshSessionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PruneSessionsCommand extends Command
{
    public function __construct(private RefreshSessionRepository $sessions)
    {
        parent::__construct('sessions:prune');
    }
    protected function configure(): void
    {
        $this->setDescription('Delete expired refresh sessions');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Expired refresh sessions deleted: ' . $this->sessions->pruneExpired());
        return Command::SUCCESS;
    }
}
