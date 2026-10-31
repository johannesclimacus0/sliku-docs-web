<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Cache\Cache;
use App\Scheduler\Scheduler;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tests\Support\IsolatedTestCase;

class SchedulerTest extends IsolatedTestCase
{
    public function testCompletedTaskIsNotExecutedBeforeNextInterval(): void
    {
        $scheduler = $this->scheduler(new InMemoryStore());
        $calls = [];
        $execute = static function (string $command) use (&$calls): int {
            $calls[] = $command;
            return 0;
        };
        self::assertSame('completed', $scheduler->run($execute)[0]['status']);
        self::assertSame('not_due', $scheduler->run($execute)[0]['status']);
        self::assertSame(['users:remind'], $calls);
        self::assertIsInt($scheduler->status()[0]['lastRun']);
    }

    public function testAnotherSchedulerCannotExecuteTaskWhileFirstHoldsLock(): void
    {
        $store = new InMemoryStore();
        $first = $this->scheduler($store);
        $second = $this->scheduler($store);
        $nestedResult = null;
        $otherCalls = 0;
        $result = $first->run(static function () use ($second, &$nestedResult, &$otherCalls): int {
            $nestedResult = $second->run(static function () use (&$otherCalls): int {
                $otherCalls++;
                return 0;
            });
            return 0;
        });
        self::assertSame('completed', $result[0]['status']);
        self::assertSame('locked', $nestedResult[0]['status']);
        self::assertSame(0, $otherCalls);
    }

    public function testExceptionReleasesLockAndDoesNotMarkTaskSuccessful(): void
    {
        $store = new InMemoryStore();
        $first = $this->scheduler($store);
        self::assertSame('failed', $first->run(static fn () => throw new \RuntimeException('failed'))[0]['status']);
        self::assertNull($first->status()[0]['lastRun']);
        $second = $this->scheduler($store);
        self::assertSame('completed', $second->run(static fn () => 0)[0]['status']);
    }

    public function testNonZeroExitCodeAllowsRetry(): void
    {
        $scheduler = $this->scheduler(new InMemoryStore());
        self::assertSame('failed', $scheduler->run(static fn () => 1)[0]['status']);
        self::assertNull($scheduler->status()[0]['lastRun']);
        self::assertSame('completed', $scheduler->run(static fn () => 0)[0]['status']);
    }

    private function scheduler(InMemoryStore $store): Scheduler
    {
        return new Scheduler(
            [['command' => 'users:remind', 'every' => 60]],
            $this->env->get(Cache::class),
            new LockFactory($store),
            new NullLogger(),
        );
    }
}
