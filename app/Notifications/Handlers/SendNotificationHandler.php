<?php

declare(strict_types=1);

namespace App\Notifications\Handlers;

use App\Modules\Account\Repositories\UserRepository;
use App\Notifications\Jobs\SendNotification;
use App\Notifications\UserRecipient;
use Symfony\Component\Notifier\NotifierInterface;

class SendNotificationHandler
{
    public function __construct(private UserRepository $users, private NotifierInterface $notifier)
    {
    }

    public function __invoke(SendNotification $job): void
    {
        $user = $this->users->findById($job->userId);
        if ($user === null) {
            return;
        }
        $this->notifier->send($job->notification, new UserRecipient($user->getId(), $user->getEmail()));
    }
}
