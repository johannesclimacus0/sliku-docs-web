<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Queue\Queue;
use App\Webhooks\Jobs\DeliverWebhook;

class Webhooks
{
    public function __construct(private WebhookEndpoints $endpoints, private WebhookStore $store, private Queue $queue)
    {
    }

    public function dispatch(string $event, array $data): array
    {
        $targets = $this->endpoints->forEvent($event);
        if ($targets === []) {
            return [];
        }
        $eventId = bin2hex(random_bytes(16));
        $body = json_encode([
            'id' => $eventId, 'event' => $event,
            'occurredAt' => new \DateTimeImmutable('now', new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
            'data' => $data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->store->transaction(function () use ($targets, $eventId, $event, $body): array {
            $ids = [];
            foreach ($targets as $name => $endpoint) {
                $id = bin2hex(random_bytes(16));
                $this->store->create($id, $eventId, $event, $name, $body);
                $this->queue->dispatch(new DeliverWebhook($id));
                $ids[] = $id;
            }
            return $ids;
        });
    }
}
