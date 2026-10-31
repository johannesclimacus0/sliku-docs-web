<?php

declare(strict_types=1);

namespace App\Validation;

use App\Exceptions\ValidationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InputValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validate(object $input): void
    {
        $details = [];

        foreach ($this->validator->validate($input) as $violation) {
            $details[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        if ($details !== []) {
            throw new ValidationException($details);
        }
    }
}
