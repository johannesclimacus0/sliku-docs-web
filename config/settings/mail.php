<?php

declare(strict_types=1);

return [
    'dsn' => $_ENV['MAILER_DSN'] ?? '',
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.test',
    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'App',
];
