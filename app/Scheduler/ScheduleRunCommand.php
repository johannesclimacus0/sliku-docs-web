<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScheduleRunCommand extends Command
{
    public function __construct(private \Psr\Container\ContainerInterface $container)
    {
        parent::__construct('schedule:run');
    }
    protected function configure(): void
    {
        $this->setDescription('Run due configured tasks under distributed locks');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $results = $this->container->get(Scheduler::class)->run(fn (string $command): int => $this->getApplication()->find($command)->run(new ArrayInput([]), $output));
        foreach ($results as $result) {
            $output->writeln($result['command'] . ': ' . $result['status']);
        }
        return in_array('failed', array_column($results, 'status'), true) ? Command::FAILURE : Command::SUCCESS;
    }
}
