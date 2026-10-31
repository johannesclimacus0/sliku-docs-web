<?php

declare(strict_types=1);

namespace App\Modules\Account\Services;

use App\Cache\Cache;
use App\Entity\User;
use App\Modules\Account\UserView;
use Doctrine\ORM\EntityManagerInterface;

class UserDirectory
{
    public function __construct(
        private EntityManagerInterface $em,
        private Cache $cache,
        private UserView $view,
    ) {
    }

    public function list(int $page, string $search): array
    {
        $revision = $this->em->getConnection()->fetchAssociative('SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS last_id, COALESCE(SUM(version), 0) AS version FROM users');
        $key = 'users_v1_' . implode('_', $revision) . '_' . $page . '_' . hash('sha256', $search);
        $hit = $this->cache->has($key);
        $data = $this->cache->remember($key, 60, function () use ($page, $search): array {
            $query = $this->em->createQueryBuilder()->from(User::class, 'u');
            if ($search !== '') {
                $query->where('LOWER(u.name) LIKE :search OR LOWER(u.email) LIKE :search')->setParameter('search', '%' . mb_strtolower($search) . '%');
            }
            $total = (int) (clone $query)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
            $users = $query->select('u')->orderBy('u.id', 'ASC')->setFirstResult(($page - 1) * 8)->setMaxResults(8)->getQuery()->getResult();
            return ['items' => array_map(fn (User $user): array => $this->view->make($user), $users), 'pagination' => ['page' => $page, 'perPage' => 8, 'total' => $total]];
        });
        return $data + ['cache' => ['hit' => $hit, 'ttl' => 60]];
    }
}
