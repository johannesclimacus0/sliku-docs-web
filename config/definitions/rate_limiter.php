<?php

declare(strict_types=1);

use App\Config\Config;
use App\Http\JsonResponse;
use App\Middleware\RateLimitMiddleware;
use App\RateLimit\RateLimitRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

return [
    'rate_limit.demo' => static fn (RateLimitRegistry $limits): RateLimitMiddleware => $limits->middleware('demo'),
    'rate_limit.password_forgot' => static fn (RateLimitRegistry $limits): RateLimitMiddleware => $limits->middleware('password_forgot'),
    'rate_limit.password_reset' => static fn (RateLimitRegistry $limits): RateLimitMiddleware => $limits->middleware('password_reset'),
    'rate_limit.verification_resend' => static fn (RateLimitRegistry $limits): RateLimitMiddleware => $limits->middleware('verification_resend'),
    'rate_limit.verification_confirm' => static fn (RateLimitRegistry $limits): RateLimitMiddleware => $limits->middleware('verification_confirm'),
    'rate_limit.register' => static fn (RateLimitRegistry $limits): RateLimitMiddleware => $limits->middleware('register'),
    'rate_limit.login' => static fn (RateLimitRegistry $limits): RateLimitMiddleware => $limits->middleware('login'),

    RateLimitRegistry::class => static function (
        JsonResponse $json,
        CacheItemPoolInterface $cache,
        Redis $redis,
        Config $config,
    ): RateLimitRegistry {
        return new RateLimitRegistry(
            $config->get('rate_limits'),
            new CacheStorage($cache),
            new LockFactory(new RedisStore($redis)),
            $json,
        );
    },
];
