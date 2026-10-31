<?php

declare(strict_types=1);

namespace App\Modules\Account\Services;

use App\Modules\Account\Jobs\SendVerificationReminder;
use App\Queue\Queue;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

class VerificationReminderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Queue $queue,
    ) {
    }

    public function queueDue(int $limit = 1000): int
    {
        if ($limit < 1 || $limit > 10000) {
            throw new \InvalidArgumentException('Limit must be between 1 and 10000');
        }
        $db = $this->em->getConnection();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cutoff = $now->modify('-1 day');
        $ids = $db->createQueryBuilder()->select('id')->from('users')
            ->where('email_verified_at IS NULL AND verification_reminder_queued_at IS NULL AND created_at <= :cutoff')
            ->setParameter('cutoff', $cutoff, Types::DATETIME_IMMUTABLE)->orderBy('id')->setMaxResults($limit)->executeQuery()->fetchFirstColumn();
        $count = 0;
        foreach ($ids as $id) {
            $count += (int) $db->transactional(function () use ($db, $id, $now, $cutoff): bool {
                $claimed = $db->executeStatement(
                    'UPDATE users SET verification_reminder_queued_at = :now WHERE id = :id AND email_verified_at IS NULL AND verification_reminder_queued_at IS NULL AND created_at <= :cutoff',
                    ['id' => $id, 'now' => $now, 'cutoff' => $cutoff],
                    ['now' => Types::DATETIME_IMMUTABLE, 'cutoff' => Types::DATETIME_IMMUTABLE]
                );
                if ($claimed !== 1) {
                    return false;
                }
                $this->queue->dispatch(new SendVerificationReminder((int) $id));
                return true;
            });
        }
        return $count;
    }
}
