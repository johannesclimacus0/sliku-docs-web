<?php

declare(strict_types=1);

namespace App\Modules\Account;

use App\Config\Config;
use App\Entity\User;
use App\Storage\Storage;

class UserView
{
    public function __construct(
        private Storage $storage,
        private Config $config,
    ) {
    }

    public function make(User $user): array
    {
        return [
            'id' => $user->getId(), 'name' => $user->getName(), 'email' => $user->getEmail(),
            'bio' => $user->getBio(), 'role' => $user->getRole()->value,
            'avatarUrl' => $user->getAvatarPath() !== null ? $this->storage->disk('public')->url($user->getAvatarPath()) : null,
            'emailVerifiedAt' => $user->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
            'emailVerificationRequired' => (bool) $this->config->get('email_verification.required', true) && $user->getEmailVerifiedAt() === null,
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
