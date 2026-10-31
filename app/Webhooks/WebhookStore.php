<?php

declare(strict_types=1);

namespace App\Webhooks;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;

class WebhookStore
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function transaction(callable $callback): mixed
    {
        return $this->em->getConnection()->transactional($callback);
    }

    public function create(string $id, string $eventId, string $event, string $endpoint, string $body): void
    {
        $this->em->getConnection()->insert('webhook_deliveries', [
            'id' => $id, 'event_id' => $eventId, 'event_name' => $event,
            'endpoint' => $endpoint, 'body' => $body, 'status' => 'pending',
            'attempts' => 0, 'created_at' => time(),
        ]);
    }

    public function find(string $id, bool $lock = false): ?array
    {
        $db = $this->em->getConnection();
        $query = $db->createQueryBuilder()->select('*')->from('webhook_deliveries')
            ->where('id = :id')->setParameter('id', $id);
        if ($lock && !$db->getDatabasePlatform() instanceof SQLitePlatform) {
            $query->forUpdate();
        }
        return $query->executeQuery()->fetchAssociative() ?: null;
    }

    public function record(array $delivery, ?int $status, ?string $error, int $durationMs): void
    {
        $number = (int) $delivery['attempts'] + 1;
        $this->em->getConnection()->insert('webhook_attempts', [
            'delivery_id' => $delivery['id'], 'attempt_no' => $number,
            'attempted_at' => time(), 'http_status' => $status,
            'error' => $error, 'duration_ms' => $durationMs,
        ]);
        $this->em->getConnection()->update('webhook_deliveries', [
            'attempts' => $number, 'last_http_status' => $status, 'last_error' => $error,
            'status' => $error === null ? 'delivered' : 'failed',
            'delivered_at' => $error === null ? time() : null,
        ], ['id' => $delivery['id']]);
    }

    public function recent(int $limit = 20): array
    {
        $rows = $this->em->getConnection()->createQueryBuilder()
            ->select('id', 'event_name', 'endpoint', 'status', 'attempts', 'last_http_status', 'created_at')
            ->from('webhook_deliveries')->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')
            ->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
        return array_map(static function (array $row): array {
            $row['attempts'] = (int) $row['attempts'];
            $row['created_at'] = (int) $row['created_at'];
            $row['last_http_status'] = $row['last_http_status'] === null ? null : (int) $row['last_http_status'];
            return $row;
        }, $rows);
    }

    public function attempts(string $id): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            'SELECT attempt_no, attempted_at, http_status, error, duration_ms FROM webhook_attempts WHERE delivery_id = ? ORDER BY attempt_no',
            [$id],
        );
    }
}
