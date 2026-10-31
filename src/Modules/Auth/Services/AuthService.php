<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Entity\User;
use App\Modules\Account\Events\UserRegistered;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Auth\Exceptions\UserAlreadyExistsException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private EventDispatcherInterface $events,
    ) {
    }

    public function register(string $email, string $password): User
    {
        $email = $email |> trim(...) |> strtolower(...);

        if ($this->userRepository->findByEmail($email) !== null) {
            throw new UserAlreadyExistsException();
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $user = new User($email, $hashed);
        $user->requestEmailVerification();

        try {
            $this->em->wrapInTransaction(function () use ($user): void {
                $this->userRepository->save($user);
                $this->events->dispatch(new UserRegistered($user));
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (!str_contains(strtolower($exception->getMessage()), '"uniq_1483a5e9e7927c74"')) {
                throw $exception;
            }

            throw new UserAlreadyExistsException($exception);
        }

        return $user;
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->userRepository->findByEmail($email |> trim(...) |> strtolower(...));

        if ($user === null || !password_verify($password, $user->getPasswordHash())) {
            return null;
        }

        return $user;
    }
}
