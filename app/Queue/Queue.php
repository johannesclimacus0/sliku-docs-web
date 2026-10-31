<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Messenger\MessageBusInterface;

class Queue
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function dispatch(object $job): void
    {
        $this->bus->dispatch($job);
    }
}
