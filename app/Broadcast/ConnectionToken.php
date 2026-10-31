<?php

declare(strict_types=1);

namespace App\Broadcast;

use App\Config\Config;
use Firebase\JWT\JWT;

class ConnectionToken
{
    public const TTL = 300;

    public function __construct(private Config $config)
    {
    }

    public static function channel(int $userId): string
    {
        return 'personal:user_' . $userId;
    }

    public function issue(int $userId): array
    {
        if (!$this->config->get('broadcast.enabled')) {
            return ['enabled' => false];
        }
        $key = $this->config->get('broadcast.token_key');
        if (!is_string($key) || strlen($key) < 32) {
            throw new \LogicException('BROADCAST_TOKEN_KEY must contain at least 32 characters');
        }
        $now = time();
        return [
            'enabled' => true,
            'url' => $this->config->get('broadcast.websocket_url'),
            'channel' => self::channel($userId),
            'expiresIn' => self::TTL,
            'token' => JWT::encode([
                'sub' => (string) $userId,
                'iat' => $now,
                'exp' => $now + self::TTL,
                'channels' => [self::channel($userId)]
            ], $key, 'HS256')
        ];
    }
}
