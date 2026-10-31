<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap\AppFactory;
use App\Config\Config;
use App\Entity\User;
use App\Http\JsonResponse;
use App\Modules\Account\Enums\UserRole;
use App\Modules\Auth\Services\JwtService;
use App\RateLimit\RateLimitRegistry;
use DI\Container;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Support\TestDatabase;
use Tests\Support\TestEnvironment;
use Twig\Environment;

abstract class DatabaseTestCase extends TestCase
{
    protected EntityManagerInterface $em;
    protected Container $container;
    protected TestEnvironment $env;
    protected App $app;
    private bool $databaseReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new TestEnvironment(TestDatabase::settings());
        try {
            $this->container = (require dirname(__DIR__, 2) . '/config/container.php')();
            foreach ([Config::class, CacheItemPoolInterface::class, FilesystemOperator::class,
                LoggerInterface::class, MailerInterface::class, TransportInterface::class, Environment::class, BodyRendererInterface::class,
                HttpClientInterface::class] as $id) {
                $this->container->set($id, $this->env->get($id));
            }
            $locks = new LockFactory(new InMemoryStore());
            $this->container->set(LockFactory::class, $locks);
            $this->container->set(RateLimitRegistry::class, new RateLimitRegistry(
                $this->container->get(Config::class)->get('rate_limits'),
                new CacheStorage($this->env->get(CacheItemPoolInterface::class)),
                $locks,
                new JsonResponse(),
            ));
            $this->em = $this->container->get(EntityManagerInterface::class);
            TestDatabase::assertSafe($this->em->getConnection());
            foreach (['users', 'refresh_sessions', 'messenger_messages', 'user_activity', 'webhook_deliveries', 'webhook_attempts', 'notifications'] as $table) {
                if ($this->em->getConnection()->fetchOne('SELECT to_regclass(?)', ['public.' . $table]) === null) {
                    throw new \RuntimeException('Run ./run test:prepare before integration tests: missing ' . $table);
                }
            }
            $this->databaseReady = true;
            $this->refreshDatabase();
            $this->app = AppFactory::create($this->container);
        } catch (\Throwable $error) {
            $this->env->close();
            if (isset($this->em)) {
                $this->em->getConnection()->close();
            }
            throw $error;
        }
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->em)) {
                $connection = $this->em->getConnection();
                while ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                try {
                    if ($this->databaseReady) {
                        $this->refreshDatabase();
                    }
                } finally {
                    $connection->close();
                }
            }
        } finally {
            if (isset($this->env)) {
                $this->env->close();
            }
            parent::tearDown();
        }
    }

    protected function refreshDatabase(): void
    {
        $connection = $this->em->getConnection();
        TestDatabase::assertSafe($connection);
        $connection->executeStatement('TRUNCATE TABLE notifications, webhook_attempts, webhook_deliveries, user_activity, refresh_sessions, messenger_messages, users RESTART IDENTITY');
        $this->em->clear();
    }

    protected function user(string $email = 'user@example.test', bool $verified = true, UserRole $role = UserRole::MEMBER): User
    {
        static $passwordHash;
        $passwordHash ??= password_hash('ChangeMe12345!', PASSWORD_DEFAULT);
        $user = new User($email, $passwordHash);
        $user->setRole($role);
        if ($verified) {
            $user->prepareEmailVerification(hash('sha256', 'fixture-token'), new \DateTimeImmutable('+1 hour'));
            self::assertTrue($user->confirmEmail('fixture-token', new \DateTimeImmutable()));
        }
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    protected function request(string $method, string $path, mixed $body = null, ?User $actor = null, ?string $cookie = null, bool $raw = false): ResponseInterface
    {
        $this->em->clear();
        $request = new ServerRequestFactory()->createServerRequest($method, 'https://api.example.test' . $path, ['REMOTE_ADDR' => '127.0.0.1']);
        parse_str(parse_url($path, PHP_URL_QUERY) ?? '', $query);
        $request = $request->withQueryParams($query);
        if ($body === null && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            $body = new \stdClass();
        }
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write($raw ? $body : json_encode($body, JSON_THROW_ON_ERROR));
            $request->getBody()->rewind();
        }
        if ($actor !== null) {
            $session = $this->container->get(\App\Modules\Auth\Repositories\RefreshSessionRepository::class)->issue($actor->getId(), false);
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->container->get(JwtService::class)->create($actor, $session['sessionId']));
        }
        if ($cookie !== null) {
            $request = $request->withCookieParams(['refresh_token' => $cookie]);
        }
        return $this->app->handle($request);
    }

    protected function json(ResponseInterface $response, int $status): array
    {
        $body = (string) $response->getBody();
        self::assertSame($status, $response->getStatusCode(), $body);
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    protected function refreshToken(ResponseInterface $response): string
    {
        self::assertSame(1, preg_match('/refresh_token=([a-f0-9]{64})/', $response->getHeaderLine('Set-Cookie'), $matches));
        return $matches[1];
    }
}
