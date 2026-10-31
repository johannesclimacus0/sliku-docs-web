<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Config\Config;
use App\Middleware\CorsMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

class CorsTest extends TestCase
{
    public static function methods(): array
    {
        return array_map(static fn (string $method): array => [$method], ['GET', 'HEAD', 'POST', 'PATCH', 'DELETE', 'OPTIONS']);
    }

    #[DataProvider('methods')]
    public function testSwaggerPreflightAllowsApiMethods(string $method): void
    {
        $origin = 'http://localhost:8081';
        $middleware = new CorsMiddleware(new Config(['cors' => ['allowed_origins' => [$origin]]]));
        $request = new ServerRequestFactory()->createServerRequest('OPTIONS', '/users/42')
            ->withHeader('Origin', $origin)
            ->withHeader('Access-Control-Request-Method', $method)
            ->withHeader('Access-Control-Request-Headers', 'authorization, content-type');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame($origin, $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        self::assertContains($method, array_map('trim', explode(',', $response->getHeaderLine('Access-Control-Allow-Methods'))));
    }
}
