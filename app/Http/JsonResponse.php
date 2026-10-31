<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\StreamFactory;

class JsonResponse
{
    public function success(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        return $this->send($response, ['data' => $data], $status);
    }

    public function error(
        ResponseInterface $response,
        string $code,
        string $message,
        int $status,
        ?array $details = null,
    ): ResponseInterface {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return $this->send($response, ['error' => $error], $status);
    }

    private function send(ResponseInterface $response, array $body, int $status): ResponseInterface
    {
        return $response
            ->withBody(new StreamFactory()->createStream(json_encode($body, JSON_THROW_ON_ERROR)))
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
