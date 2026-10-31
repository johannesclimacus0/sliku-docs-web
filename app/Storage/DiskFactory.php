<?php

declare(strict_types=1);

namespace App\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

class DiskFactory
{
    public function create(array $settings): FilesystemOperator
    {
        if (($settings['driver'] ?? null) !== 'local') {
            throw new \InvalidArgumentException('Unsupported storage driver: ' . ($settings['driver'] ?? '(missing)'));
        }
        if (!is_string($settings['root'] ?? null) || $settings['root'] === '') {
            throw new \InvalidArgumentException('Local storage requires a root directory');
        }

        $visibility = $settings['visibility'] ?? Visibility::PRIVATE;
        if (!in_array($visibility, [Visibility::PUBLIC, Visibility::PRIVATE], true)) {
            throw new \InvalidArgumentException('Invalid disk visibility');
        }
        return new Filesystem(new LocalFilesystemAdapter(
            $settings['root'],
            new PortableVisibilityConverter(defaultForDirectories: $visibility),
        ), ['visibility' => $visibility, 'directory_visibility' => $visibility]);
    }
}
