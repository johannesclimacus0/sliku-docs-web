<?php

declare(strict_types=1);

namespace App\Exceptions;

interface ApiErrorException
{
    public function errorCode(): string;
}
