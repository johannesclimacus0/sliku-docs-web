<?php

declare(strict_types=1);

namespace App\Broadcast\Jobs;

class PublishNotification
{
    public function __construct(public string $id, public int $userId, public string $event, public string $notificationId)
    {
    }
}
