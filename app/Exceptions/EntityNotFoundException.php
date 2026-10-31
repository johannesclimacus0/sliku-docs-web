<?php

declare(strict_types=1);

namespace App\Exceptions;

class EntityNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Ресурс не найден');
    }

    public function errorCode(): string
    {
        return 'ENTITY_NOT_FOUND';
    }
}
