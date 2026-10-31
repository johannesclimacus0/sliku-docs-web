<?php

declare(strict_types=1);

namespace App\Modules\Account\Handlers;

use App\Modules\Account\Jobs\RecordUserActivity;
use App\Modules\Account\Repositories\ActivityRepository;
use Psr\Log\LoggerInterface;

class RecordUserActivityHandler
{
    public function __construct(
        private ActivityRepository $activity,
        private LoggerInterface $logger,
    ) {
    }
    public function __invoke(RecordUserActivity $job): void
    {
        if ($job->fail) {
            throw new \RuntimeException('Intentional demo job failure for user ' . $job->userId);
        }
        $this->activity->record($job->eventId, $job->userId, $job->action);
        $this->logger->info('User activity recorded by worker', ['user_id' => $job->userId, 'event_id' => $job->eventId, 'action' => $job->action]);
    }
}
