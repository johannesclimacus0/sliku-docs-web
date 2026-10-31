<?php

declare(strict_types=1);

namespace App\Broadcast\Handlers;

use App\Broadcast\Jobs\PublishEvent;
use App\Broadcast\Jobs\PublishNotification;
use App\Broadcast\Publisher;
use App\Modules\Account\Repositories\UserRepository;

class PublishEventHandler
{
    public function __construct(private Publisher $publisher, private UserRepository $users)
    {
    }

    public function __invoke(PublishEvent|PublishNotification $job): void
    {
        if ($this->users->findById($job->userId) !== null) {
            $this->publisher->publish($job);
        }
    }
}
