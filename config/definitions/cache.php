<?php

declare(strict_types=1);

use App\Cache\Cache;
use App\Config\Config;
use App\Debug\Profiler;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

return [
    Redis::class => static function (Config $config): Redis {
        return RedisAdapter::createConnection(
            $config->get('cache.redis_url'),
            ['class' => Redis::class, 'timeout' => 2, 'read_timeout' => 2],
        );
    },

    CacheItemPoolInterface::class => static function (Redis $redis, Config $config, Profiler $profiler): CacheItemPoolInterface {
        $pool = new RedisAdapter($redis, $config->get('cache.namespace'));
        if ($profiler->clockwork() !== null) {
            $pool = new TraceableAdapter($pool, static fn (): bool => !$profiler->isActive());
            $profiler->traceCache($pool);
        }

        return $pool;
    },

    Cache::class => static function (CacheItemPoolInterface $pool): Cache {
        return new Cache($pool);
    },
];
