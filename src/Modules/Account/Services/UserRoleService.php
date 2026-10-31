<?php

declare(strict_types=1);

namespace App\Modules\Account\Services;

use App\Entity\User;
use App\Exceptions\ValidationException;
use App\Modules\Account\Enums\UserRole;
use App\Modules\Account\Events\UserProfileUpdated;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Account\Security\UserVoter;
use App\Security\Authorization;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserRoleService
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private Authorization $authorization,
        private EventDispatcherInterface $events,
    ) {
    }

    public function change(User $target, UserRole $role, ?User $actor = null): void
    {
        if ($actor !== null) {
            $this->authorization->denyAccessUnlessGranted($actor, UserVoter::MANAGE, User::class);
        }
        $this->em->wrapInTransaction(function () use ($target, $role): void {
            $admins = $this->em->createQueryBuilder()->select('u')->from(User::class, 'u')->where('u.role = :role')->setParameter('role', UserRole::ADMIN->value)
                ->orderBy('u.id')->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getResult();
            $user = $this->users->findForUpdate($target->getId()) ?? throw new \LogicException('User no longer exists');
            if ($user->getRole() === $role) {
                return;
            }
            if ($user->getRole() === UserRole::ADMIN && $role !== UserRole::ADMIN && count($admins) <= 1) {
                throw new ValidationException(['role' => ['Нельзя понизить последнего администратора']]);
            }
            $user->setRole($role);
            $this->events->dispatch(new UserProfileUpdated($user, ['role']));
        });
    }
}
