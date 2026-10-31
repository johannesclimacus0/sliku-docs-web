<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Entity\User;
use App\Http\JsonResponse;
use App\Middleware\JwtAuthMiddleware;
use App\Modules\Auth\Input\VerifyEmailInput;
use App\Modules\Auth\Services\EmailVerificationService;
use App\Validation\InputValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EmailVerificationController
{
    public function __construct(
        private EmailVerificationService $service,
        private InputValidator $validator,
        private JsonResponse $json,
    ) {
    }

    public function confirm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $input = new VerifyEmailInput($body->userId ?? null, $body->token ?? null);
        $this->validator->validate($input);
        $this->service->confirm($input->userId, $input->token);

        return $this->json->success($response, ['message' => 'Email подтверждён'])
            ->withHeader('Cache-Control', 'no-store');
    }

    public function resend(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new \LogicException('Authentication middleware is missing');
        }

        $this->service->resend($user);

        return $this->json->success($response, [
            'message' => 'Если email ещё не подтверждён, письмо будет отправлено',
        ], 202)->withHeader('Cache-Control', 'no-store');
    }
}
