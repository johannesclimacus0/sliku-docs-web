<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\InvalidRequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;
use stdClass;

class RequireJsonBodyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $mediaType = $request->getHeaderLine('Content-Type')
                |> (fn (string $value): string => explode(';', $value)[0])
                |> trim(...)
                |> strtolower(...);

        if ($request->getMethod() === 'POST' && $mediaType === 'multipart/form-data'
            && RouteContext::fromRequest($request)->getRoute()?->getName() === 'auth.avatar.upload') {
            return $handler->handle($request);
        }

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)
            && !$request->getParsedBody() instanceof stdClass) {
            throw new InvalidRequestException('Тело запроса должно быть JSON-объектом');
        }

        return $handler->handle($request);
    }
}
