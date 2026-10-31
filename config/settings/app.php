<?php

declare(strict_types=1);

return [
    'app_name' => $_ENV['APP_NAME'] ?? 'Slim',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'jwt_key' => $_ENV['JWT_KEY'] ?? '',
];
