<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Exceptions\InvalidRequestException;
use App\Modules\Account\Events\UserPasswordReset;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Auth\Jobs\SendPasswordReset;
use App\Modules\Auth\Repositories\RefreshSessionRepository;
use App\Queue\Queue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PasswordResetService
{
    public function __construct(
        private UserRepository $users,
        private RefreshSessionRepository $sessions,
        private EntityManagerInterface $em,
        private Queue $queue,
        private EventDispatcherInterface $events,
    ) {
    }

    public function request(string $email): void
    {
        $this->em->wrapInTransaction(function () use ($email): void {
            $found = $this->users->findByEmail(strtolower(trim($email)));
            if ($found === null) {
                return;
            }
            $user = $this->users->findForUpdate($found->getId());
            if ($user === null) {
                return;
            }
            $version = $user->requestPasswordReset();
            $this->queue->dispatch(new SendPasswordReset($user->getId(), $version));
        });
    }

    public function reset(int $userId, string $token, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->em->wrapInTransaction(function () use ($userId, $token, $hash): void {
            $user = $this->users->findForUpdate($userId);
            if ($user === null || !$user->resetPassword($token, $hash, new \DateTimeImmutable('now', new \DateTimeZone('UTC')))) {
                throw new InvalidRequestException('Ссылка недействительна, уже использована или срок её действия истёк');
            }
            $this->sessions->revokeAllForUser($userId);
            $this->events->dispatch(new UserPasswordReset($user));
        });
    }
}
