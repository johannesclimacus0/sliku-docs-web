<?php

declare(strict_types=1);

namespace App\Modules\Auth\Http;

use App\Entity\User;
use App\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ServerRequestInterface;

class AuthenticatedUser
{
    public static function fromRequest(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        return $user instanceof User ? $user : throw new \LogicException('Authentication middleware is missing');
    }
}
