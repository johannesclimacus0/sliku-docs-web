<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http;

use App\Config\Config;
use App\Modules\Auth\Repositories\RefreshSessionRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RefreshCookie
{
    private const NAME = 'refresh_token';

    public function __construct(private Config $config)
    {
    }

    public function read(ServerRequestInterface $request): ?string
    {
        $token = $request->getCookieParams()[self::NAME] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function set(ResponseInterface $response, string $token, bool $remember): ResponseInterface
    {
        $parts = [self::NAME . '=' . $token, 'Path=/', 'HttpOnly', 'SameSite=Lax'];

        if ($remember) {
            $parts[] = 'Max-Age=' . RefreshSessionRepository::REMEMBER_TTL;
        }

        if ($this->secure()) {
            $parts[] = 'Secure';
        }

        return $response->withHeader('Set-Cookie', implode('; ', $parts));
    }

    public function clear(ResponseInterface $response): ResponseInterface
    {
        $parts = [self::NAME . '=', 'Path=/', 'HttpOnly', 'SameSite=Lax', 'Max-Age=0'];

        if ($this->secure()) {
            $parts[] = 'Secure';
        }

        return $response->withHeader('Set-Cookie', implode('; ', $parts));
    }

    private function secure(): bool
    {
        return $this->config->get('app.env') !== 'local';
    }
}
