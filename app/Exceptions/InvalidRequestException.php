<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidRequestException extends InvalidInputException
{
    public function __construct(string $message = 'Некорректный запрос')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'INVALID_REQUEST';
    }
}
