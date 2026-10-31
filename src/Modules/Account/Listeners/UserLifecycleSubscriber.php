<?php

declare(strict_types=1);

namespace App\Modules\Account\Listeners;

use App\Modules\Account\Events\{UserEmailVerified, UserEvent, UserPasswordReset, UserProfileUpdated, UserRegistered};
use App\Modules\Account\Jobs\RecordUserActivity;
use App\Modules\Account\Notifications\AccountNotification;
use App\Modules\Auth\Jobs\{SendEmailVerification, SendWelcomeEmail};
use App\Notifications\Notifications;
use App\Queue\Queue;
use App\Webhooks\Webhooks;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Queue $queue,
        private Webhooks $webhooks,
        private Notifications $notifications,
    ) {
    }

    public function onChanged(UserEvent $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->queue->dispatch(new SendEmailVerification($event->userId, $event->verificationVersion));
        }
        if ($event instanceof UserEmailVerified) {
            $this->queue->dispatch(new SendWelcomeEmail($event->userId));
        }
        $this->queue->dispatch(new RecordUserActivity($event->userId, $event->id, $event::NAME));
        $this->notifications->send($event->userId, new AccountNotification($event));
        $this->webhooks->dispatch($event::NAME, $event->data);
    }

    public static function getSubscribedEvents(): array
    {
        return [UserRegistered::class => 'onChanged', UserEmailVerified::class => 'onChanged',
            UserProfileUpdated::class => 'onChanged', UserPasswordReset::class => 'onChanged'];
    }
}
