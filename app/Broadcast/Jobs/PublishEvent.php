<?php

declare(strict_types=1);

namespace App\Broadcast\Jobs;

class PublishEvent
{
    public function __construct(public string $id, public int $userId, public string $event, public array $data)
    {
    }
}
