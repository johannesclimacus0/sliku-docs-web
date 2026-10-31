<?php

declare(strict_types=1);

namespace App\Console\UI;

use App\Queue\FailureTraceStamp;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class FailedJobCommand extends Command
{
    public function __construct(private ContainerInterface $container)
    {
        parent::__construct('ui:job');
    }

    protected function configure(): void
    {
        $this->setHidden(true)->setDescription('Inspect a failed job as JSON')
            ->addArgument('id', InputArgument::REQUIRED, 'Failed message ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string) $input->getArgument('id');
        if (preg_match('/\A[1-9][0-9]*\z/', $id) !== 1) {
            throw new \InvalidArgumentException('A positive failed job ID is required');
        }
        $envelope = $this->container->get('queue.failed')->find($id);
        if ($envelope === null) {
            $output->writeln('{"error":"Failed job not found. It may already have been retried or deleted."}');
            return Command::FAILURE;
        }
        $failure = $envelope->last(ErrorDetailsStamp::class);
        $trace = $envelope->last(FailureTraceStamp::class);
        $retries = $trace?->retries ?? 0;
        foreach ($envelope->all(RedeliveryStamp::class) as $stamp) {
            $retries = max($retries, $stamp->getRetryCount());
        }
        $data = ['id' => $id, 'class' => $envelope->getMessage()::class, 'retries' => $retries, 'attempts' => $retries + 1,
            'failedAt' => $trace?->failedAt ?? $envelope->last(RedeliveryStamp::class)?->getRedeliveredAt()->format(DATE_ATOM),
            'error' => $failure === null ? null : ['class' => $failure->getExceptionClass(),
                'message' => mb_substr($failure->getExceptionMessage(), 0, 16000), 'code' => $failure->getExceptionCode()],
            'trace' => $trace?->frames ?? []];
        $output->writeln(json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), OutputInterface::OUTPUT_RAW);
        return Command::SUCCESS;
    }
}
