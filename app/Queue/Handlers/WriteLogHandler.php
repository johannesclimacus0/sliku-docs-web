<?php

declare(strict_types=1);

namespace App\Queue\Handlers;

use App\Queue\Jobs\WriteLog;
use Psr\Log\LoggerInterface;

class WriteLogHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(WriteLog $job): void
    {
        $this->logger->info($job->message, [
            'source' => 'queue',
        ]);
    }
}
