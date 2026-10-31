<?php

declare(strict_types=1);

namespace App\Modules\Account\Repositories;

use Doctrine\ORM\EntityManagerInterface;

class ActivityRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function record(string $id, int $userId, string $action): void
    {
        $this->em->getConnection()->executeStatement(
            'INSERT INTO user_activity (id, user_id, action, created_at) SELECT :id, :user, :action, :time WHERE EXISTS (SELECT 1 FROM users WHERE id = :user) ON CONFLICT (id) DO NOTHING',
            ['id' => $id, 'user' => $userId, 'action' => $action, 'time' => time()],
        );
    }

    public function forUser(int $id): array
    {
        $rows = $this->em->getConnection()->createQueryBuilder()->select('id', 'action', 'created_at')
            ->from('user_activity')->where('user_id = :id')->setParameter('id', $id)
            ->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults(15)->executeQuery()->fetchAllAssociative();
        return array_map(static fn (array $row): array => ['id' => $row['id'], 'action' => $row['action'], 'created_at' => (int) $row['created_at']], $rows);
    }
}
