<?php

declare(strict_types=1);

use App\Config\Config;
use App\Storage\DiskFactory;
use League\Flysystem\FilesystemOperator;

return [
    FilesystemOperator::class => static function (Config $config, DiskFactory $factory): FilesystemOperator {
        $name = $config->get('storage.default', 'public');
        $disks = $config->get('storage.disks', []);
        if (!isset($disks[$name]) || !is_array($disks[$name])) {
            throw new InvalidArgumentException('Storage disk is not configured: ' . $name);
        }

        return $factory->create($disks[$name]);
    },
];
