<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Config\Config;
use App\Entity\User;
use App\Modules\Auth\Repositories\RefreshSessionRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use LogicException;
use UnexpectedValueException;

class JwtService
{
    public const int ACCESS_TTL = 900;

    private string $key;

    public function __construct(Config $config, private RefreshSessionRepository $sessions)
    {
        $key = $config->get('app.jwt_key');

        if (!is_string($key) || strlen($key) < 32) {
            throw new LogicException('JWT_KEY must contain at least 32 bytes');
        }

        $this->key = $key;
    }

    public function create(User $user, string $sessionId): string
    {
        $id = $user->getId();

        if ($id === null) {
            throw new LogicException('Cannot create a token for an unsaved user');
        }

        if (preg_match('/\A[a-f0-9]{32}\z/', $sessionId) !== 1) {
            throw new LogicException('Invalid session ID');
        }

        $now = time();

        return JWT::encode([
            'sub' => (string) $id,
            'sid' => $sessionId,
            'iat' => $now,
            'exp' => $now + self::ACCESS_TTL,
        ], $this->key, 'HS256');
    }

    public function verify(string $token): int
    {
        $claims = JWT::decode($token, new Key($this->key, 'HS256'));
        $subject = $claims->sub ?? null;

        if (!is_string($subject)) {
            throw new UnexpectedValueException('Invalid token subject');
        }

        $id = filter_var($subject, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            throw new UnexpectedValueException('Invalid token subject');
        }

        $sessionId = $claims->sid ?? null;
        if (!is_string($sessionId) || preg_match('/\A[a-f0-9]{32}\z/', $sessionId) !== 1
            || !is_int($claims->iat ?? null) || !is_int($claims->exp ?? null)
            || !$this->sessions->isActive($sessionId, $id)) {
            throw new UnexpectedValueException('Invalid or revoked session');
        }

        return $id;
    }
}
