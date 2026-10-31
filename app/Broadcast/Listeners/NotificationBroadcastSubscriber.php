<?php

declare(strict_types=1);

namespace App\Broadcast\Listeners;

use App\Broadcast\Broadcast;
use App\Notifications\Events\NotificationCreated;
use App\Notifications\Events\NotificationEvent;
use App\Notifications\Events\NotificationRead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationBroadcastSubscriber implements EventSubscriberInterface
{
    public function __construct(private Broadcast $broadcast)
    {
    }

    public function changed(NotificationEvent $event): void
    {
        $name = $event instanceof NotificationCreated ? 'notification.created' : 'notification.read';
        $this->broadcast->toUser($event->userId, $name, ['notificationId' => $event->notificationId]);
    }

    public static function getSubscribedEvents(): array
    {
        return [NotificationCreated::class => 'changed', NotificationRead::class => 'changed'];
    }
}
