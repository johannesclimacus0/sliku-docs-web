<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private const string ALLOWED_METHODS = 'GET, HEAD, POST, PATCH, DELETE, OPTIONS';
    private const string ALLOWED_HEADERS = 'Content-Type, Authorization, X-Request-ID';

    public function __construct(private Config $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $allowed = $origin !== '' && in_array($origin, $this->config->get('cors.allowed_origins', []), true);

        if ($allowed && $request->getMethod() === 'OPTIONS'
            && $request->hasHeader('Access-Control-Request-Method')) {
            return $this->withCorsHeaders(new Response()->withStatus(204), $origin, true);
        }

        $response = $handler->handle($request);

        if ($origin === '') {
            return $response;
        }

        if (!$allowed) {
            return $response->withAddedHeader('Vary', 'Origin');
        }

        return $this->withCorsHeaders($response, $origin, false);
    }

    private function withCorsHeaders(ResponseInterface $response, string $origin, bool $preflight): ResponseInterface
    {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Expose-Headers', 'Retry-After, X-Request-ID, X-Response-Time, X-Clockwork-Id, X-Clockwork-Version, X-Clockwork-Path')
            ->withAddedHeader('Vary', 'Origin');

        if ($preflight) {
            $response = $response
                ->withHeader('Access-Control-Allow-Methods', self::ALLOWED_METHODS)
                ->withHeader('Access-Control-Allow-Headers', self::ALLOWED_HEADERS)
                ->withHeader('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}
