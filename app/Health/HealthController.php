<?php

declare(strict_types=1);

namespace App\Health;

use App\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HealthController
{
    public function __construct(private HealthCheck $health, private JsonResponse $json)
    {
    }

    public function ready(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $report = $this->health->check();

        return $this->json->success(
            $response->withHeader('Cache-Control', 'no-store'),
            $report,
            $report['status'] === 'ok' ? 200 : 503,
        );
    }

    public function live(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json->success($response->withHeader('Cache-Control', 'no-store'), ['status' => 'ok']);
    }
}
