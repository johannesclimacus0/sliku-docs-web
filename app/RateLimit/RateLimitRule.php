<?php

declare(strict_types=1);

namespace App\RateLimit;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimitRule
{
    public function __construct(
        private RateLimiterFactory $factory,
        private Closure $key,
    ) {
    }

    public function consume(ServerRequestInterface $request): ?RateLimit
    {
        $key = ($this->key)($request);

        if ($key === null) {
            return null;
        }

        return $this->factory->create(hash('sha256', $key))->consume(1);
    }
}
