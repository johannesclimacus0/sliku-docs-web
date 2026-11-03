<?php

declare(strict_types=1);

use DI\Container;
use DI\ContainerBuilder;

return static function (): Container {
    $builder = new ContainerBuilder();
    $files = glob(__DIR__ . '/definitions/*.php') ?: [];
    sort($files);
    foreach ($files as $file) {
        $builder->addDefinitions($file);
    }

    return $builder->build();
};
