<?php

declare(strict_types=1);

namespace App\Exceptions;

abstract class NotFoundException extends \RuntimeException implements ApiErrorException
{
}
