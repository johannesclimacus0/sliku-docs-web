<?php

declare(strict_types=1);

return [
    'path' => $_ENV['DOCS_PATH'] ?? '/docs-content',
    'default_version' => $_ENV['DOCS_VERSION'] ?? '1.x',
    'versions' => [
        '1.x' => '1.x',
    ],
];
