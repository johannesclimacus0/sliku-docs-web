<?php

declare(strict_types=1);

namespace App\Notifications\Jobs;

use App\Notifications\Notification;

class SendNotification
{
    public function __construct(public int $userId, public Notification $notification)
    {
    }
}
