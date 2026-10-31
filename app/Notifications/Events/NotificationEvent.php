<?php

declare(strict_types=1);

namespace App\Notifications\Events;

abstract class NotificationEvent
{
    public function __construct(public string $notificationId, public int $userId, public string $type, public ?int $readAt = null)
    {
    }
}
