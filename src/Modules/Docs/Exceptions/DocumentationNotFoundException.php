<?php

declare(strict_types=1);

namespace App\Modules\Docs\Exceptions;

use App\Exceptions\NotFoundException;

class DocumentationNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Статья документации не найдена');
    }

    public function errorCode(): string
    {
        return 'DOCUMENTATION_NOT_FOUND';
    }
}
