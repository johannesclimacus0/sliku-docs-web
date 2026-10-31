<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Bootstrap\AppFactory;
use App\Config\Config;
use App\Entity\User;
use App\Exceptions\EntityNotFoundException;
use App\Routing\RouteEntityBindingStrategy;
use DI\Container;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class RoutingTest extends TestCase
{
    public function testLivenessDoesNotConstructDoctrineOrRedis(): void
    {
        $container = (require dirname(__DIR__, 2) . '/config/container.php')();
        $container->set(Config::class, new Config(['app' => ['env' => 'testing', 'debug' => false]]));
        $container->set(LoggerInterface::class, new NullLogger());
        $container->set(EntityManagerInterface::class, function (): never {
            self::fail('Liveness must not construct Doctrine');
        });
        $container->set(\Redis::class, function (): never {
            self::fail('Liveness must not connect to Redis');
        });

        $response = AppFactory::create($container)->handle(new ServerRequestFactory()->createServerRequest('GET', '/health/live'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['data' => ['status' => 'ok']], json_decode((string) $response->getBody(), true));
    }

    public function testEntityParameterStillLoadsFromDoctrine(): void
    {
        $user = new User('user@example.test', 'unused');
        $metadata = $this->createMock(ClassMetadataFactory::class);
        $metadata->expects(self::once())->method('isTransient')->with(User::class)->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('getMetadataFactory')->willReturn($metadata);
        $em->expects(self::once())->method('find')->with(User::class, '42')->willReturn($user);
        $container = new Container([EntityManagerInterface::class => $em]);
        $handler = function (User $user, ResponseInterface $response): ResponseInterface {
            $response->getBody()->write($user->getEmail());
            return $response;
        };

        $response = $container->get(RouteEntityBindingStrategy::class)(
            $handler,
            new ServerRequestFactory()->createServerRequest('GET', '/users/42'),
            new Response(),
            ['user' => '42'],
        );

        self::assertSame('user@example.test', (string) $response->getBody());
    }

    public function testMissingEntityRemainsNotFound(): void
    {
        $metadata = $this->createStub(ClassMetadataFactory::class);
        $metadata->method('isTransient')->willReturn(false);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadata);
        $em->method('find')->willReturn(null);
        $container = new Container([EntityManagerInterface::class => $em]);

        $this->expectException(EntityNotFoundException::class);
        $container->get(RouteEntityBindingStrategy::class)(
            fn (User $user, ResponseInterface $response): ResponseInterface => $response,
            new ServerRequestFactory()->createServerRequest('GET', '/users/42'),
            new Response(),
            ['user' => '42'],
        );
    }
}
