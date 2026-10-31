<?php

declare(strict_types=1);

namespace App\Modules\Account\Fixtures;

use App\Entity\User;
use App\Modules\Account\Enums\UserRole;
use App\Modules\Account\Repositories\ActivityRepository;
use App\Modules\Account\Repositories\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class DemoUsers
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private ActivityRepository $activity,
    ) {
    }
    public function load(string $password): array
    {
        $names = ['admin', 'member', 'pending', 'some other dude'];
        return $this->em->wrapInTransaction(function () use ($password, $names): array {
            $created = 0;
            foreach ($names as $index => $name) {
                $email = match ($index) {
                    0 => 'admin@example.test', 1 => 'member@example.test', 2 => 'pending@example.test', default => 'demo' . $index . '@example.test'
                };
                if ($this->users->findByEmail($email) !== null) {
                    continue;
                }
                $user = new User($email, password_hash($password, PASSWORD_DEFAULT), $name, new \DateTimeImmutable('-2 days', new \DateTimeZone('UTC')));
                $user->updateProfile($name, $index === 0 ? 'mlikuuu hi i am an admin' : 'i have no rights and i must left');
                if ($index === 0) {
                    $user->setRole(UserRole::ADMIN);
                }
                $user->requestEmailVerification();
                if ($index !== 2) {
                    $token = bin2hex(random_bytes(16));
                    $user->prepareEmailVerification(hash('sha256', $token), new \DateTimeImmutable('+1 hour'));
                    $user->confirmEmail($token, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                    $user->markWelcomeSent();
                }
                $this->users->save($user);
                $this->activity->record(bin2hex(random_bytes(16)), $user->getId(), 'user.fixture_created');
                ++$created;
            }
            return ['created' => $created, 'kept' => count($names) - $created];
        });
    }
}
