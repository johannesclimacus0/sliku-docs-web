<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Cache\Cache;
use RuntimeException;
use Tests\Support\IsolatedTestCase;

class CacheTest extends IsolatedTestCase
{
    public function testValuesCanBeWrittenReadAndDeleted(): void
    {
        $cache = $this->env->get(Cache::class);
        self::assertSame('fallback', $cache->get('missing', 'fallback'));
        self::assertTrue($cache->set('user', ['id' => 42], 60));
        self::assertTrue($cache->has('user'));
        self::assertSame(['id' => 42], $cache->get('user'));
        self::assertTrue($cache->delete('user'));
        self::assertFalse($cache->has('user'));
    }

    public function testExpiredValueIsNotReturned(): void
    {
        $cache = $this->env->get(Cache::class);
        $cache->set('expired', 'old', -1);
        self::assertFalse($cache->has('expired'));
        self::assertNull($cache->get('expired'));
    }

    public function testRememberCachesEvenNullAndFalse(): void
    {
        $cache = $this->env->get(Cache::class);
        foreach ([null, false, ['id' => 42]] as $index => $value) {
            $calls = 0;
            $callback = static function () use (&$calls, $value): mixed {
                $calls++;
                return $value;
            };
            self::assertSame($value, $cache->remember('value_' . $index, 60, $callback));
            self::assertSame($value, $cache->remember('value_' . $index, 60, $callback));
            self::assertSame(1, $calls);
        }
    }

    public function testRememberForeverRecomputesAfterDeletion(): void
    {
        $cache = $this->env->get(Cache::class);
        $calls = 0;
        $callback = static function () use (&$calls): int {
            return ++$calls;
        };
        self::assertSame(1, $cache->rememberForever('forever', $callback));
        self::assertSame(1, $cache->rememberForever('forever', $callback));
        $cache->delete('forever');
        self::assertSame(2, $cache->rememberForever('forever', $callback));
    }

    public function testFailedCallbackDoesNotPoisonCache(): void
    {
        $cache = $this->env->get(Cache::class);
        try {
            $cache->remember('failed', 60, static fn () => throw new RuntimeException('unavailable'));
            self::fail('Callback exception must propagate');
        } catch (RuntimeException $exception) {
            self::assertSame('unavailable', $exception->getMessage());
        }
        self::assertFalse($cache->has('failed'));
        self::assertSame('recovered', $cache->remember('failed', 60, static fn () => 'recovered'));
    }
}
