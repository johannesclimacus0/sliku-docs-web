<?php

declare(strict_types=1);

namespace App\Queue;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class WorkerStateSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger)
    {
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $event->addStamps(FailureTraceStamp::capture($event->getThrowable(), RedeliveryStamp::getRetryCountFromEnvelope($event->getEnvelope())));
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->close();
        }
    }

    public function onRunning(WorkerRunningEvent $event): void
    {
        if ($event->isWorkerIdle()) {
            return;
        }

        if ($this->em->isOpen()) {
            $this->em->clear();
        } else {
            $this->logger->warning('Stopping worker after EntityManager was closed');
            $event->getWorker()->stop();
        }

        $this->em->getConnection()->close();
    }

    public function onStopped(WorkerStoppedEvent $event): void
    {
        $this->em->getConnection()->close();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => ['onFailed', 512],
            WorkerRunningEvent::class => ['onRunning', -2048],
            WorkerStoppedEvent::class => 'onStopped',
        ];
    }
}
