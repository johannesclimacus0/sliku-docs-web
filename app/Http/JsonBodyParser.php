<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\InvalidRequestException;
use JsonException;
use stdClass;

class JsonBodyParser
{
    public function __invoke(string $input): stdClass
    {
        try {
            $body = json_decode($input, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidRequestException('Некорректный JSON');
        }

        if (!$body instanceof stdClass) {
            throw new InvalidRequestException('Тело запроса должно быть JSON-объектом');
        }

        return $body;
    }
}
