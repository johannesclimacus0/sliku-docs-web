<?php

declare(strict_types=1);

namespace App\Exceptions;

class ValidationException extends InvalidInputException
{
    public function __construct(private array $details)
    {
        parent::__construct('Проверьте введённые данные');
    }

    public function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }

    public function details(): array
    {
        return $this->details;
    }
}
