<?php

declare(strict_types=1);

return [
    'required' => filter_var($_ENV['EMAIL_VERIFICATION_REQUIRED'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'ttl' => max(60, (int) ($_ENV['EMAIL_VERIFICATION_TTL'] ?? 3600)),
    'frontend_url' => $_ENV['FRONTEND_URL'] ?? (($_ENV['APP_ENV'] ?? 'production') === 'local' ? 'http://localhost:5173' : ''),
];
