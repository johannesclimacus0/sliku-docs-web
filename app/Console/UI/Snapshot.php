<?php

declare(strict_types=1);

namespace App\Console\UI;

use App\Health\HealthCheck;
use App\Scheduler\Scheduler;
use App\Webhooks\WebhookStore;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

class Snapshot
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function read(): array
    {
        $result = ['updatedAt' => gmdate('H:i:s') . ' UTC', 'errors' => []];
        $sections = [
            'health' => fn (): array => $this->container->get(HealthCheck::class)->check(),
            'queue' => function (): array {
                $db = $this->container->get(EntityManagerInterface::class)->getConnection();
                $counts = ['async' => 0, 'failed' => 0];
                foreach ($db->fetchAllAssociative('SELECT queue_name, COUNT(*) AS total FROM messenger_messages GROUP BY queue_name') as $row) {
                    $counts[$row['queue_name']] = (int) $row['total'];
                }
                return ['counts' => $counts, 'failed' => $db->fetchAllAssociative(
                    'SELECT id, created_at FROM messenger_messages WHERE queue_name = ? ORDER BY id DESC LIMIT 20',
                    ['failed'],
                )];
            },
            'schedule' => fn (): array => $this->container->get(Scheduler::class)->status(),
            'webhooks' => fn (): array => array_map(
                static fn (array $row): array => array_intersect_key($row, array_flip(['id', 'event_name', 'status', 'attempts', 'last_http_status'])),
                $this->container->get(WebhookStore::class)->recent(10),
            ),
        ];
        foreach ($sections as $name => $load) {
            try {
                $result[$name] = $load();
            } catch (\Throwable $error) {
                $result[$name] = null;
                $result['errors'][$name] = get_debug_type($error);
            }
        }
        return $result;
    }
}
