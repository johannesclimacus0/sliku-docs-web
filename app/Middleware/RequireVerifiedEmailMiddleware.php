<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Config;
use App\Entity\User;
use App\Exceptions\EmailNotVerifiedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequireVerifiedEmailMiddleware implements MiddlewareInterface
{
    public function __construct(private Config $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new \LogicException('JwtAuthMiddleware must run before RequireVerifiedEmailMiddleware');
        }

        if ($this->config->get('email_verification.required', true) && $user->getEmailVerifiedAt() === null) {
            throw new EmailNotVerifiedException();
        }

        return $handler->handle($request);
    }
}
