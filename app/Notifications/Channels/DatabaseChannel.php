<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\Notification;
use App\Notifications\NotificationStore;
use App\Notifications\UserRecipient;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class DatabaseChannel implements ChannelInterface
{
    public function __construct(private NotificationStore $store)
    {
    }

    public function notify(SymfonyNotification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        if (!$notification instanceof Notification || !$recipient instanceof UserRecipient) {
            throw new \LogicException('The database channel requires an application Notification and UserRecipient');
        }
        $this->store->add($recipient->userId, $notification);
    }

    public function supports(SymfonyNotification $notification, RecipientInterface $recipient): bool
    {
        return $notification instanceof Notification && $recipient instanceof UserRecipient;
    }
}
