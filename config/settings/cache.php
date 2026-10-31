<?php

declare(strict_types=1);

return [
    'redis_url' => $_ENV['REDIS_URL'] ?? 'redis://redis:6379',
    'namespace' => $_ENV['CACHE_NAMESPACE'] ?? 'app',
];
