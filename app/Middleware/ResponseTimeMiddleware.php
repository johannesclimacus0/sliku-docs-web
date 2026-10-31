<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ResponseTimeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = hrtime(true);

        $response = $handler->handle($request);

        $milliseconds = round((hrtime(true) - $start) / 1_000_000, 2);

        return $response->withHeader(
            'X-Response-Time',
            $milliseconds . 'ms',
        );
    }
}
