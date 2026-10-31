<?php

declare(strict_types=1);

use App\Broadcast\Listeners\NotificationBroadcastSubscriber;
use App\Modules\Account\Listeners\UserLifecycleSubscriber;
use App\Notifications\Listeners\NotificationWebhookSubscriber;

return [
    'subscribers' => [
        UserLifecycleSubscriber::class,
        NotificationBroadcastSubscriber::class,
        NotificationWebhookSubscriber::class
    ],
    'listeners' => [],
];
