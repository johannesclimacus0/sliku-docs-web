<?php

declare(strict_types=1);

namespace App\Storage;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\WhitespacePathNormalizer;

class Disk
{
    public function __construct(private FilesystemOperator $disk, private ?string $publicUrl = null)
    {
    }

    public function put(string $path, string $contents): void
    {
        $this->disk->write($path, $contents);
    }

    public function get(string $path): string
    {
        return $this->disk->read($path);
    }

    /** @param resource $stream */
    public function putStream(string $path, $stream): void
    {
        $this->disk->writeStream($path, $stream);
    }

    /** @return resource */
    public function readStream(string $path)
    {
        return $this->disk->readStream($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk->fileExists($path);
    }

    public function delete(string $path): void
    {
        $this->disk->delete($path);
    }

    public function url(string $path): string
    {
        if ($this->publicUrl === null) {
            throw new \LogicException('This disk has no public URL');
        }
        $path = new WhitespacePathNormalizer()->normalizePath($path);

        $encoded = explode('/', $path)
            |> (fn (array $segments): array => array_map('rawurlencode', $segments))
            |> (fn (array $segments): string => implode('/', $segments));

        return rtrim($this->publicUrl, '/') . '/' . $encoded;
    }
}
