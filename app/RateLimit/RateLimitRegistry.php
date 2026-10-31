<?php

declare(strict_types=1);

namespace App\RateLimit;

use App\Http\JsonResponse;
use App\Middleware\RateLimitMiddleware;
use InvalidArgumentException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

class RateLimitRegistry
{
    public function __construct(
        private array $policies,
        private StorageInterface $storage,
        private LockFactory $locks,
        private JsonResponse $json,
    ) {
    }

    public function middleware(string $name): RateLimitMiddleware
    {
        $policy = $this->policies[$name] ?? null;

        if ($policy === null) {
            throw new InvalidArgumentException("Unknown rate limit policy: $name");
        }

        $rules = [];

        foreach ($policy['rules'] as $definition) {
            $factory = new RateLimiterFactory([
                'id' => $definition['id'],
                'policy' => $definition['policy'] ?? 'fixed_window',
                'limit' => $definition['limit'],
                'interval' => $definition['interval'],
            ], $this->storage, $this->locks);

            $rules[] = new RateLimitRule($factory, $definition['key']);
        }

        return new RateLimitMiddleware($rules, $policy['message'], $this->json);
    }
}
