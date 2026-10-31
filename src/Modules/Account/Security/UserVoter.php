<?php

declare(strict_types=1);

namespace App\Modules\Account\Security;

use App\Entity\User;
use App\Modules\Account\Enums\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const string VIEW = 'USER_VIEW';
    public const string EDIT = 'USER_EDIT';
    public const string MANAGE = 'USER_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return ($attribute === self::MANAGE && $subject === User::class)
            || (in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof User);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return false;
        }
        if ($user->getRole() === UserRole::ADMIN) {
            return true;
        }
        return $attribute !== self::MANAGE && $subject->getId() === $user->getId();
    }
}
