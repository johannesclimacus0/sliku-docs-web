<?php

declare(strict_types=1);

namespace App\Modules\Account\Commands;

use App\Modules\Account\Services\VerificationReminderService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class QueueVerificationRemindersCommand extends Command
{
    public function __construct(private VerificationReminderService $reminders)
    {
        parent::__construct('users:remind');
    }
    protected function configure(): void
    {
        $this->setDescription('Queue one verification reminder for accounts unverified after 24 hours');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        new SymfonyStyle($input, $output)->success('User reminders queued: ' . $this->reminders->queueDue());
        return Command::SUCCESS;
    }
}
