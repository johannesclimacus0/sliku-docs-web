<?php

declare(strict_types=1);

namespace App\Exceptions;

class EmailNotVerifiedException extends ForbiddenException
{
    public function __construct()
    {
        parent::__construct('Подтвердите email, чтобы выполнить это действие');
    }

    public function errorCode(): string
    {
        return 'EMAIL_NOT_VERIFIED';
    }
}
