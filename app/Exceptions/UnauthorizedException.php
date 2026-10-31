<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class UnauthorizedException extends RuntimeException implements ApiErrorException
{
    public function __construct(string $message = 'Необходимо войти в аккаунт', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return 'UNAUTHORIZED';
    }
}
