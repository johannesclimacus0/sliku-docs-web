<?php

declare(strict_types=1);

namespace App\Modules\Account\Events;

class UserEmailVerified extends UserEvent
{
    public const string NAME = 'user.verified';
}
