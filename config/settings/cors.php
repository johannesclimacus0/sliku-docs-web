<?php

declare(strict_types=1);

$defaultOrigins = ($_ENV['APP_ENV'] ?? 'production') === 'local'
    ? 'http://localhost:5173'
    : '';
$origins = explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? $defaultOrigins);

return [
    'allowed_origins' => array_values(array_filter(
        array_map('trim', $origins),
        static fn (string $origin): bool => $origin !== '',
    )),
];
