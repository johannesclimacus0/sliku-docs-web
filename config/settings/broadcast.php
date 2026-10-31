<?php

declare(strict_types=1);

return [
    'enabled' => filter_var($_ENV['BROADCAST_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'token_key' => $_ENV['BROADCAST_TOKEN_KEY'] ?? '',
    'api_key' => $_ENV['BROADCAST_API_KEY'] ?? '',
    'api_url' => $_ENV['BROADCAST_API_URL'] ?? 'http://centrifugo:8000',
    'websocket_url' => $_ENV['BROADCAST_WEBSOCKET_URL'] ?? 'ws://localhost:8000/connection/websocket',
];
