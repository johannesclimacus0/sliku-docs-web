<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Events\NotificationCreated;
use App\Notifications\Events\NotificationRead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NotificationStore
{
    private const PER_PAGE = 20;

    public function __construct(private EntityManagerInterface $em, private EventDispatcherInterface $events)
    {
    }

    public function add(int $userId, Notification $notification): void
    {
        $this->em->getConnection()->transactional(function () use ($userId, $notification): void {
            $inserted = $this->em->getConnection()->executeStatement(
                'INSERT INTO notifications (id, user_id, type, title, body, data, created_at, read_at) VALUES (:id, :user, :type, :title, :body, :data, :created, NULL) ON CONFLICT (id, user_id) DO NOTHING',
                ['id' => $notification->id, 'user' => $userId, 'type' => $notification->type,
                    'title' => $notification->getSubject(), 'body' => $notification->getContent(),
                    'data' => json_encode((object) $notification->data, JSON_THROW_ON_ERROR), 'created' => time()],
            );
            if ($inserted > 0) {
                $this->events->dispatch(new NotificationCreated($notification->id, $userId, $notification->type));
            }
        });
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public function list(int $userId, int $page, bool $unreadOnly): array
    {
        $query = $this->em->getConnection()->createQueryBuilder()->from('notifications')
            ->where('user_id = :user')->setParameter('user', $userId);
        if ($unreadOnly) {
            $query->andWhere('read_at IS NULL');
        }
        $total = (int) (clone $query)->select('COUNT(*)')->executeQuery()->fetchOne();
        $rows = $query->select('*')->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')
            ->setFirstResult(($page - 1) * self::PER_PAGE)->setMaxResults(self::PER_PAGE)->executeQuery()->fetchAllAssociative();
        return ['items' => array_map(static fn (array $row): array => [
            'id' => $row['id'], 'type' => $row['type'], 'title' => $row['title'], 'body' => $row['body'],
            'data' => json_decode($row['data'], false, 512, JSON_THROW_ON_ERROR),
            'createdAt' => (int) $row['created_at'], 'readAt' => $row['read_at'] === null ? null : (int) $row['read_at'],
        ], $rows), 'unreadCount' => $this->unreadCount($userId),
            'pagination' => ['page' => $page, 'perPage' => self::PER_PAGE, 'total' => $total]];
    }

    public function markRead(int $userId, string $id): bool
    {
        return $this->em->getConnection()->transactional(function () use ($userId, $id): bool {
            $row = $this->em->getConnection()->executeQuery(
                'UPDATE notifications SET read_at = :now WHERE user_id = :user AND id = :id AND read_at IS NULL RETURNING id, type, read_at',
                ['now' => time(), 'user' => $userId, 'id' => $id],
            )->fetchAssociative();
            if ($row !== false) {
                $this->events->dispatch(new NotificationRead($id, $userId, $row['type'], (int) $row['read_at']));
                return true;
            }
            return $this->em->getConnection()->fetchOne('SELECT 1 FROM notifications WHERE user_id = ? AND id = ?', [$userId, $id]) !== false;
        });
    }

    public function markAllRead(int $userId): void
    {
        $this->em->getConnection()->transactional(function () use ($userId): void {
            $rows = $this->em->getConnection()->executeQuery(
                'UPDATE notifications SET read_at = :now WHERE user_id = :user AND read_at IS NULL RETURNING id, type, read_at',
                ['now' => time(), 'user' => $userId],
            )->fetchAllAssociative();
            foreach ($rows as $row) {
                $this->events->dispatch(new NotificationRead($row['id'], $userId, $row['type'], (int) $row['read_at']));
            }
        });
    }
}
