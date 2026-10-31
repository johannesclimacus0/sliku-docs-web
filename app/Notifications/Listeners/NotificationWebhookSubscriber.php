<?php

declare(strict_types=1);

namespace App\Notifications\Listeners;

use App\Notifications\Events\NotificationCreated;
use App\Notifications\Events\NotificationEvent;
use App\Notifications\Events\NotificationRead;
use App\Webhooks\Webhooks;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationWebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(private Webhooks $webhooks)
    {
    }

    public function onNotification(NotificationEvent $event): void
    {
        $this->webhooks->dispatch($event instanceof NotificationCreated ? 'notification.created' : 'notification.read', [
            'notificationId' => $event->notificationId,
            'userId' => $event->userId,
            'type' => $event->type,
            'readAt' => $event->readAt,
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [NotificationCreated::class => 'onNotification', NotificationRead::class => 'onNotification'];
    }
}
