<?php

declare(strict_types=1);

return [
    'driver' => 'pdo_pgsql',
    'host' => $_ENV['DB_HOST'] ?? 'db',
    'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
    'connect_timeout' => max(1, (int) ($_ENV['DB_CONNECT_TIMEOUT'] ?? 3)),
    'name' => $_ENV['DB_NAME'] ?? 'slim',
    'username' => $_ENV['DB_USER'] ?? 'slim',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'unmanaged_tables' => ['refresh_sessions', 'messenger_messages', 'webhook_deliveries', 'webhook_attempts', 'user_activity', 'notifications'],
];
