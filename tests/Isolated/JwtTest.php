<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Config\Config;
use App\Entity\User;
use App\Modules\Auth\Repositories\RefreshSessionRepository;
use App\Modules\Auth\Services\JwtService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class JwtTest extends TestCase
{
    private const KEY = 'test-key-with-at-least-thirty-two-bytes';
    private const SESSION = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testTokenContainsSessionAndAuthenticatesWhileItExists(): void
    {
        $sessions = new JwtTestSessions();
        $jwt = $this->jwt($sessions);
        $token = $jwt->create($this->user(), self::SESSION);
        $claims = JWT::decode($token, new Key(self::KEY, 'HS256'));
        self::assertSame(self::SESSION, $claims->sid ?? null);
        self::assertSame(42, $jwt->verify($token));
    }

    public function testRevokedSessionRejectsPreviouslyIssuedToken(): void
    {
        $sessions = new JwtTestSessions();
        $jwt = $this->jwt($sessions);
        $token = $jwt->create($this->user(), self::SESSION);
        $sessions->active = false;
        $this->expectException(UnexpectedValueException::class);
        $jwt->verify($token);
    }

    public function testSessionMustBelongToTheTokenUser(): void
    {
        $jwt = $this->jwt(new JwtTestSessions());
        $token = JWT::encode(['sub' => '43', 'sid' => self::SESSION, 'iat' => time(), 'exp' => time() + 900], self::KEY, 'HS256');
        $this->expectException(UnexpectedValueException::class);
        $jwt->verify($token);
    }

    public function testOldTokensWithoutSessionAreRejected(): void
    {
        $jwt = $this->jwt(new JwtTestSessions());
        $token = JWT::encode(['sub' => '42', 'iat' => time(), 'exp' => time() + 900], self::KEY, 'HS256');
        $this->expectException(UnexpectedValueException::class);
        $jwt->verify($token);
    }

    private function jwt(JwtTestSessions $sessions): JwtService
    {
        return new JwtService(new Config(['app' => ['jwt_key' => self::KEY]]), $sessions);
    }

    private function user(): User
    {
        $user = new User('user@example.test', 'unused');
        new \ReflectionProperty($user, 'id')->setValue($user, 42);
        return $user;
    }
}

class JwtTestSessions extends RefreshSessionRepository
{
    public bool $active = true;

    public function __construct()
    {
    }

    public function isActive(string $sessionId, int $userId): bool
    {
        return $this->active && $userId === 42 && $sessionId === str_repeat('a', 32);
    }
}
