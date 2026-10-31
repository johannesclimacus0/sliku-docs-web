<?php

declare(strict_types=1);
return ['endpoints' => [
    'notifications' => [
        'url' => $_ENV['WEBHOOK_NOTIFICATION_URL'] ?? '',
        'secret' => $_ENV['WEBHOOK_NOTIFICATION_SECRET'] ?? '',
        'events' => ['notification.created', 'notification.read'],
    ],
    'users' => [
        'url' => $_ENV['WEBHOOK_USER_URL'] ?? '',
        'secret' => $_ENV['WEBHOOK_USER_SECRET'] ?? '',
        'events' => ['user.registered', 'user.verified', 'user.updated', 'user.password_reset', 'user.demo'],
    ],
]];
