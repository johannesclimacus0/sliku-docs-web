<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Entity\User;
use App\Exceptions\InvalidRequestException;
use App\Modules\Account\Events\UserEmailVerified;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Auth\Jobs\SendEmailVerification;
use App\Queue\Queue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EmailVerificationService
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $em,
        private Queue $queue,
        private EventDispatcherInterface $events,
    ) {
    }

    public function resend(User $actor): void
    {
        $this->em->wrapInTransaction(function () use ($actor): void {
            $user = $this->users->findForUpdate($actor->getId());
            if ($user === null || $user->getEmailVerifiedAt() !== null) {
                return;
            }

            $version = $user->requestEmailVerification();
            $this->queue->dispatch(new SendEmailVerification($user->getId(), $version));
        });
    }

    public function confirm(int $userId, string $token): void
    {
        $this->em->wrapInTransaction(function () use ($userId, $token): void {
            $user = $this->users->findForUpdate($userId);
            if ($user === null || !$user->confirmEmail($token, new \DateTimeImmutable('now', new \DateTimeZone('UTC')))) {
                throw new InvalidRequestException('Ссылка недействительна, уже использована или срок её действия истёк');
            }

            $this->events->dispatch(new UserEmailVerified($user));
        });
    }
}
