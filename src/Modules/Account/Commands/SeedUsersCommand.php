<?php

declare(strict_types=1);

namespace App\Modules\Account\Commands;

use App\Config\Config;
use App\Modules\Account\Fixtures\DemoUsers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedUsersCommand extends Command
{
    public function __construct(private DemoUsers $fixtures, private Config $config)
    {
        parent::__construct('db:seed');
    }
    protected function configure(): void
    {
        $this->setDescription('Create idempotent demo users without sending mail')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for new demo users', 'ChangeMe12345!')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Explicitly allow demo credentials outside local/testing');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->config->get('app.env'), ['local', 'testing'], true) && !$input->getOption('force')) {
            $io->error('Demo fixtures require local/testing environment, or explicit --force.');
            return Command::FAILURE;
        }
        $password = $input->getOption('password');
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            $io->error('Password must be 12–72 bytes without null bytes.');
            return Command::INVALID;
        }
        $result = $this->fixtures->load($password);
        $io->success('Created: ' . $result['created'] . '; unchanged existing users: ' . $result['kept']);
        $io->table(['Email', 'Role', 'Email state'], [['admin@example.test', 'admin', 'verified'], ['member@example.test', 'member', 'verified'], ['pending@example.test', 'member', 'unverified, reminder due']]);
        $io->note('Existing users and passwords are never overwritten. No jobs or emails were sent.');
        return Command::SUCCESS;
    }
}
