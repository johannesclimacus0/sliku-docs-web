<?php

declare(strict_types=1);

namespace App\Modules\Account\Enums;

enum UserRole: string
{
    case MEMBER = 'member';
    case ADMIN = 'admin';
}
