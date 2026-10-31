<?php

declare(strict_types=1);

namespace App\Modules\Account\Commands;

use App\Modules\Account\Enums\UserRole;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Account\Services\UserRoleService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SetUserRoleCommand extends Command
{
    public function __construct(private UserRepository $users, private UserRoleService $roles)
    {
        parent::__construct('user:role');
    }

    protected function configure(): void
    {
        $this->setDescription('Assign a user role')
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('role', InputArgument::REQUIRED, 'member or admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $role = UserRole::tryFrom((string) $input->getArgument('role'));
        if ($role === null) {
            $io->error('Unknown role. Use member or admin.');
            return Command::INVALID;
        }

        $email = strtolower(trim((string) $input->getArgument('email')));
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            $io->error('User not found.');
            return Command::FAILURE;
        }
        try {
            $this->roles->change($user, $role);
        } catch (\App\Exceptions\ValidationException $error) {
            $io->error(implode('; ', array_merge(...array_values($error->details()))));
            return Command::FAILURE;
        }

        $io->success('Role assigned: ' . $role->value);
        return Command::SUCCESS;
    }
}
