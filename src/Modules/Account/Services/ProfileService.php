<?php

declare(strict_types=1);

namespace App\Modules\Account\Services;

use App\Entity\User;
use App\Modules\Account\Events\UserProfileUpdated;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Account\Security\UserVoter;
use App\Security\Authorization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProfileService
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private Authorization $authorization,
        private EventDispatcherInterface $events,
    ) {
    }

    public function update(User $target, User $actor, string $name, string $bio): User
    {
        $this->authorization->denyAccessUnlessGranted($actor, UserVoter::EDIT, $target);
        return $this->em->wrapInTransaction(function () use ($target, $name, $bio): User {
            $user = $this->users->findForUpdate($target->getId()) ?? throw new \LogicException('User no longer exists');
            $user->updateProfile($name, $bio);
            $this->events->dispatch(new UserProfileUpdated($user, ['name', 'bio']));
            return $user;
        });
    }
}
