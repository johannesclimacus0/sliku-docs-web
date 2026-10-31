<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ForbiddenException extends RuntimeException implements ApiErrorException
{
    public function __construct(string $message = 'Недостаточно прав', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return 'FORBIDDEN';
    }
}
