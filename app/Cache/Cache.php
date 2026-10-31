<?php

declare(strict_types=1);

namespace App\Cache;

use DateInterval;
use Psr\Cache\CacheItemPoolInterface;

class Cache
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $item = $this->pool->getItem($key);
        $item->set($value);
        $item->expiresAfter($ttl);

        return $this->pool->save($item);
    }

    public function has(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    public function delete(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    public function remember(string $key, int|DateInterval|null $ttl, callable $callback): mixed
    {
        $item = $this->pool->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $callback();
        $item->set($value);
        $item->expiresAfter($ttl);
        $this->pool->save($item);

        return $value;
    }
}
