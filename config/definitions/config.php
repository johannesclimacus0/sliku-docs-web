<?php

declare(strict_types=1);

use App\Config\Config;

return [
    Config::class => static function (): Config {
        $items = [];
        foreach (glob(__DIR__ . '/../settings/*.php') ?: [] as $file) {
            $items[basename($file, '.php')] = require $file;
        }
        return new Config($items);
    },
];
