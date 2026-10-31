<?php

declare(strict_types=1);

namespace App\Broadcast;

use App\Http\JsonResponse;
use App\Modules\Auth\Http\AuthenticatedUser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ConnectionController
{
    public function __construct(private ConnectionToken $tokens, private JsonResponse $json)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json->success($response, $this->tokens->issue(AuthenticatedUser::fromRequest($request)->getId()))
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
