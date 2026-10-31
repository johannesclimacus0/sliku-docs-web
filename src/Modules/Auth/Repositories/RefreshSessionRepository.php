<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class RefreshSessionRepository
{
    public const int SESSION_TTL = 12 * 60 * 60;
    public const int REMEMBER_TTL = 365 * 24 * 60 * 60;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array{token: string, sessionId: string} */
    public function issue(int $userId, bool $remember): array
    {
        $token = bin2hex(random_bytes(32));
        $sessionId = bin2hex(random_bytes(16));
        $this->insert($this->em->getConnection(), $token, $sessionId, $userId, $remember);

        return ['token' => $token, 'sessionId' => $sessionId];
    }

    /** @return array{token: string, sessionId: string, userId: int, remember: bool}|null */
    public function rotate(string $token): ?array
    {
        if (!$this->isToken($token)) {
            return null;
        }

        return $this->em->getConnection()->transactional(function (Connection $connection) use ($token): ?array {
            $row = $connection->executeQuery(
                'DELETE FROM refresh_sessions WHERE token_hash = :hash AND expires_at > :now RETURNING session_id, user_id, remember_me',
                ['hash' => hash('sha256', $token), 'now' => time()],
            )->fetchAssociative();

            if ($row === false) {
                return null;
            }

            $userId = (int) $row['user_id'];
            $remember = (int) $row['remember_me'] === 1;
            $newToken = bin2hex(random_bytes(32));

            $this->insert($connection, $newToken, $row['session_id'], $userId, $remember);

            return ['token' => $newToken, 'sessionId' => $row['session_id'], 'userId' => $userId, 'remember' => $remember];
        });
    }

    public function isActive(string $sessionId, int $userId): bool
    {
        return $this->em->getConnection()->fetchOne(
            'SELECT 1 FROM refresh_sessions WHERE session_id = :session AND user_id = :user AND expires_at > :now',
            ['session' => $sessionId, 'user' => $userId, 'now' => time()],
        ) !== false;
    }

    public function revoke(string $token): void
    {
        if (!$this->isToken($token)) {
            return;
        }

        $this->em->getConnection()->delete('refresh_sessions', [
            'token_hash' => hash('sha256', $token),
        ]);
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->em->getConnection()->delete('refresh_sessions', ['user_id' => $userId]);
    }

    public function pruneExpired(): int
    {
        return $this->em->getConnection()->executeStatement(
            'DELETE FROM refresh_sessions WHERE expires_at <= :now',
            ['now' => time()],
        );
    }

    private function insert(Connection $connection, string $token, string $sessionId, int $userId, bool $remember): void
    {
        $connection->insert('refresh_sessions', [
            'token_hash' => hash('sha256', $token),
            'session_id' => $sessionId,
            'user_id' => $userId,
            'remember_me' => $remember ? 1 : 0,
            'expires_at' => time() + ($remember ? self::REMEMBER_TTL : self::SESSION_TTL),
        ]);
    }

    private function isToken(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }
}
