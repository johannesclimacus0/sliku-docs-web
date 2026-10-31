<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\UnauthorizedException;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Auth\Services\JwtService;
use DomainException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;

class JwtAuthMiddleware implements MiddlewareInterface
{
    public const string USER_ATTRIBUTE = 'auth.user';

    public function __construct(
        private JwtService $jwt,
        private UserRepository $users,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = trim($request->getHeaderLine('Authorization'));

        if (preg_match('/^Bearer[ \t]+(\S+)$/i', $authorization, $matches) !== 1) {
            throw new UnauthorizedException();
        }

        try {
            $userId = $this->jwt->verify($matches[1]);
        } catch (DomainException|UnexpectedValueException $exception) {
            throw new UnauthorizedException('Токен недействителен или срок его действия истёк', $exception);
        }

        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new UnauthorizedException('Токен недействителен или срок его действия истёк');
        }

        return $handler->handle($request->withAttribute(self::USER_ATTRIBUTE, $user));
    }
}
