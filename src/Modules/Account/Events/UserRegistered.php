<?php

declare(strict_types=1);

namespace App\Modules\Account\Events;

class UserRegistered extends UserEvent
{
    public const string NAME = 'user.registered';
}
