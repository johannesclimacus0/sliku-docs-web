<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

use App\Exceptions\ConflictException;
use Throwable;

class UserAlreadyExistsException extends ConflictException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Пользователь с таким email уже существует', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'USER_ALREADY_EXISTS';
    }
}
