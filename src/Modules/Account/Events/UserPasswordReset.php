<?php

declare(strict_types=1);

namespace App\Modules\Account\Events;

class UserPasswordReset extends UserEvent
{
    public const string NAME = 'user.password_reset';
}
