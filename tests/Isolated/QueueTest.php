<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Queue\Queue;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Tests\Support\ExampleJob;
use Tests\Support\TestEnvironment;

class QueueTest extends TestCase
{
    public function testDispatchQueuesMessageAndHandlerRunsOnlyAfterReceiving(): void
    {
        $handled = [];
        $env = new TestEnvironment(handlers: [ExampleJob::class => [static function (ExampleJob $job) use (&$handled): void {
            $handled[] = $job->userId;
        }]]);
        try {
            $env->get(Queue::class)->dispatch(new ExampleJob(42));
            self::assertSame([], $handled);
            self::assertCount(1, $env->transport->getSent());
            $envelope = iterator_to_array($env->transport->get())[0];
            self::assertSame(42, $envelope->getMessage()->userId);
            $env->get(MessageBusInterface::class)->dispatch($envelope->with(new ReceivedStamp('async')));
            $env->transport->ack($envelope);
            self::assertSame([42], $handled);
            self::assertCount(1, $env->transport->getAcknowledged());
            self::assertSame([], iterator_to_array($env->transport->get()));
        } finally {
            $env->close();
        }
    }

    public function testWorkerRetriesAndMovesExhaustedJobToFailed(): void
    {
        $attempts = 0;
        $env = new TestEnvironment(handlers: [ExampleJob::class => [static function () use (&$attempts): never {
            $attempts++;
            throw new RuntimeException('service unavailable');
        }]]);
        try {
            $env->get(Queue::class)->dispatch(new ExampleJob(42));
            $env->work(maxRetries: 2);
            self::assertSame(3, $attempts);
            self::assertCount(1, $env->failed->getSent());
            self::assertSame(42, $env->failed->getSent()[0]->getMessage()->userId);
            self::assertSame([], iterator_to_array($env->transport->get()));
        } finally {
            $env->close();
        }
    }

    public function testWorkerRecoversOnRetryAndAcknowledgesJob(): void
    {
        $attempts = 0;
        $env = new TestEnvironment(handlers: [ExampleJob::class => [static function () use (&$attempts): void {
            if (++$attempts === 1) {
                throw new RuntimeException('first attempt fails');
            }
        }]]);
        try {
            $env->get(Queue::class)->dispatch(new ExampleJob(42));
            $env->work();
            self::assertSame(2, $attempts);
            self::assertCount(1, $env->transport->getAcknowledged());
            self::assertCount(0, $env->failed->getSent());
        } finally {
            $env->close();
        }
    }

    public function testHandlerFailureIsVisibleAndMessageIsNotAcknowledged(): void
    {
        $env = new TestEnvironment(handlers: [ExampleJob::class => [static fn () => throw new RuntimeException('temporary failure')]]);
        try {
            $env->get(Queue::class)->dispatch(new ExampleJob(42));
            $envelope = iterator_to_array($env->transport->get())[0];
            try {
                $env->get(MessageBusInterface::class)->dispatch($envelope->with(new ReceivedStamp('async')));
                self::fail('Handler failure must propagate');
            } catch (HandlerFailedException $exception) {
                self::assertSame('temporary failure', $exception->getPrevious()->getMessage());
            }
            self::assertCount(0, $env->transport->getAcknowledged());
            self::assertCount(1, iterator_to_array($env->transport->get()));
        } finally {
            $env->close();
        }
    }
}
