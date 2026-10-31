<?php

declare(strict_types=1);

namespace App\Exceptions;

abstract class InvalidInputException extends \InvalidArgumentException implements ApiErrorException
{
}
