<?php

declare(strict_types=1);

namespace App\Modules\Account\Repositories;

use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

class UserRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function findById(int $id): ?User
    {
        return $this->em->find(User::class, $id);
    }

    public function findForUpdate(int $id): ?User
    {
        return $this->em->createQueryBuilder()
            ->select('user')
            ->from(User::class, 'user')
            ->where('user.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }

    public function save(User $user): void
    {
        $this->em->persist($user);

        $this->em->flush();
    }
}
