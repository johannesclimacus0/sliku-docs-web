<?php

declare(strict_types=1);

namespace App\Modules\Account\Events;

use App\Entity\User;

abstract class UserEvent
{
    public string $id;
    public int $userId;
    public array $data;
    public int $verificationVersion;

    public function __construct(User $user, array $fields = [])
    {
        $this->id = bin2hex(random_bytes(16));
        $this->userId = $user->getId() ?? throw new \LogicException('User must be persisted before dispatch');
        $this->verificationVersion = $user->getEmailVerificationVersion();
        $this->data = ['userId' => $this->userId, 'name' => $user->getName(), 'role' => $user->getRole()->value,
            'verified' => $user->getEmailVerifiedAt() !== null, 'fields' => $fields];
    }
}
