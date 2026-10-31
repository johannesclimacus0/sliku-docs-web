<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Jobs\SendNotification;
use App\Queue\Queue;

class Notifications
{
    public function __construct(private Queue $queue)
    {
    }

    public function send(int $userId, Notification $notification): string
    {
        $this->queue->dispatch(new SendNotification($userId, $notification));
        return $notification->id;
    }
}
