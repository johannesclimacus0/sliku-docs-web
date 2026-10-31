<?php

declare(strict_types=1);

namespace App\Storage;

use App\Config\Config;
use League\Flysystem\FilesystemOperator;

class Storage
{
    private array $disks = [];
    private string $default;

    public function __construct(FilesystemOperator $filesystem, private Config $config, private DiskFactory $factory)
    {
        $this->default = $config->get('storage.default', 'public');
        $settings = $this->settings($this->default);
        $this->disks[$this->default] = new Disk($filesystem, $settings['url'] ?? null);
    }

    public function disk(?string $name = null): Disk
    {
        $name ??= $this->default;
        if (!isset($this->disks[$name])) {
            $settings = $this->settings($name);
            $this->disks[$name] = new Disk($this->factory->create($settings), $settings['url'] ?? null);
        }

        return $this->disks[$name];
    }

    public function put(string $path, string $contents): void
    {
        $this->disk()->put($path, $contents);
    }

    public function get(string $path): string
    {
        return $this->disk()->get($path);
    }

    /** @param resource $stream */
    public function putStream(string $path, $stream): void
    {
        $this->disk()->putStream($path, $stream);
    }

    /** @return resource */
    public function readStream(string $path)
    {
        return $this->disk()->readStream($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    private function settings(string $name): array
    {
        $disks = $this->config->get('storage.disks', []);
        if (!isset($disks[$name]) || !is_array($disks[$name])) {
            throw new \InvalidArgumentException('Storage disk is not configured: ' . $name);
        }

        return $disks[$name];
    }
}
