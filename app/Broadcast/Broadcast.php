<?php

declare(strict_types=1);

namespace App\Broadcast;

use App\Broadcast\Jobs\PublishEvent;
use App\Config\Config;
use App\Queue\Queue;

class Broadcast
{
    public function __construct(private Queue $queue, private Config $config)
    {
    }

    public function toUser(int $userId, string $event, array $data = []): ?string
    {
        if ($userId <= 0 || preg_match('/\A[a-zA-Z][a-zA-Z0-9_.-]{0,99}\z/', $event) !== 1) {
            throw new \InvalidArgumentException('A positive user ID and a valid event name are required');
        }
        json_encode($data, JSON_THROW_ON_ERROR);
        if (!$this->config->get('broadcast.enabled')) {
            return null;
        }
        $id = bin2hex(random_bytes(16));
        $this->queue->dispatch(new PublishEvent($id, $userId, $event, $data));
        return $id;
    }
}
