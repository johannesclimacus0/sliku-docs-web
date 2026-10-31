<?php

declare(strict_types=1);

return [
    'default' => $_ENV['STORAGE_DISK'] ?? 'public',
    'disks' => [
        'public' => [
            'driver' => 'local',
            'visibility' => 'public',
            'root' => dirname(__DIR__, 2) . '/var/storage/public',
            'url' => $_ENV['STORAGE_PUBLIC_URL'] ?? '/media',
        ],
        'private' => [
            'driver' => 'local',
            'visibility' => 'private',
            'root' => dirname(__DIR__, 2) . '/var/storage/private',
        ],
    ],
];
