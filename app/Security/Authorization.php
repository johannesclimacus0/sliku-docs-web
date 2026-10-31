<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Exceptions\ForbiddenException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

class Authorization
{
    public function __construct(private AccessDecisionManagerInterface $decisions)
    {
    }

    public function isGranted(User $user, string $action, mixed $subject = null): bool
    {
        if ($user->getId() === null) {
            return false;
        }

        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        return $this->decisions->decide($token, [$action], $subject);
    }

    public function denyAccessUnlessGranted(User $user, string $action, mixed $subject = null): void
    {
        if (!$this->isGranted($user, $action, $subject)) {
            throw new ForbiddenException();
        }
    }
}
