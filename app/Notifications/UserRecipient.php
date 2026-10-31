<?php

declare(strict_types=1);

namespace App\Notifications;

use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class UserRecipient implements EmailRecipientInterface
{
    public function __construct(public int $userId, private string $email)
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
