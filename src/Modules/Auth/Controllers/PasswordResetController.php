<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\JsonResponse;
use App\Modules\Auth\Input\ForgotPasswordInput;
use App\Modules\Auth\Input\ResetPasswordInput;
use App\Modules\Auth\Services\PasswordResetService;
use App\Validation\InputValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PasswordResetController
{
    public function __construct(
        private PasswordResetService $service,
        private InputValidator $validator,
        private JsonResponse $json,
    ) {
    }

    public function request(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $input = new ForgotPasswordInput($body->email ?? null);
        $this->validator->validate($input);
        $this->service->request($input->email);

        return $this->json->success($response, ['message' => 'Письмо для восстановления будет отправлено'], 202)
            ->withHeader('Cache-Control', 'no-store');
    }

    public function reset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $input = new ResetPasswordInput($body->userId ?? null, $body->token ?? null, $body->password ?? null);
        $this->validator->validate($input);
        $this->service->reset($input->userId, $input->token, $input->password);

        return $this->json->success($response, ['message' => 'Пароль изменён. Войдите с новым паролем'])
            ->withHeader('Cache-Control', 'no-store');
    }
}
