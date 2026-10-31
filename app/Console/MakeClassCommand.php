<?php

declare(strict_types=1);

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeClassCommand extends Command
{
    public function __construct(private string $type, private ClassGenerator $generator)
    {
        parent::__construct('make:' . $type);
    }
    protected function configure(): void
    {
        $this->setDescription('Generate a ' . $this->type . ' without overwriting existing files')
            ->addArgument('name', InputArgument::REQUIRED, 'PascalCase name, optionally nested')
            ->addOption('module', null, InputOption::VALUE_REQUIRED, 'Target module', 'Account');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $path = $this->generator->generate($this->type, $input->getOption('module'), $input->getArgument('name'));
        } catch (\Throwable $error) {
            $io->error($error->getMessage());
            return Command::FAILURE;
        }
        $io->success('Created ' . $path);
        $io->note('Register routes, queue handlers, commands, listeners or voters explicitly where applicable.');
        return Command::SUCCESS;
    }
}
