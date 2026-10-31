<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Entity\User;
use App\Exceptions\UnauthorizedException;
use App\Http\JsonResponse;
use App\Middleware\JwtAuthMiddleware;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Account\UserView;
use App\Modules\Auth\Http\RefreshCookie;
use App\Modules\Auth\Input\LoginInput;
use App\Modules\Auth\Input\RegisterInput;
use App\Modules\Auth\Repositories\RefreshSessionRepository;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\JwtService;
use App\Validation\InputValidator;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    public function __construct(
        private AuthService $auth,
        private JsonResponse $json,
        private JwtService $jwt,
        private RefreshSessionRepository $sessions,
        private RefreshCookie $refreshCookie,
        private UserRepository $users,
        private InputValidator $validator,
        private UserView $view,
    ) {
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        $input = new RegisterInput(
            $body->email ?? null,
            $body->password ?? null,
        );

        $this->validator->validate($input);

        $user = $this->auth->register($input->email, $input->password);

        return $this->signedInResponse($request, $response, $user, false, 201);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $input = new LoginInput(
            $body->email ?? null,
            $body->password ?? null,
            property_exists($body, 'remember') ? $body->remember : false,
        );

        $this->validator->validate($input);

        $user = $this->auth->authenticate($input->email, $input->password);

        if ($user === null) {
            throw new UnauthorizedException('Неверный email или пароль');
        }

        return $this->signedInResponse($request, $response, $user, $input->remember);
    }

    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $this->refreshCookie->read($request);
        $session = $token !== null ? $this->sessions->rotate($token) : null;

        if ($session === null) {
            return $this->invalidRefreshResponse($response);
        }

        $user = $this->users->findById($session['userId']);

        if ($user === null) {
            return $this->invalidRefreshResponse($response);
        }

        return $this->tokenResponse(
            $this->refreshCookie->set($response, $session['token'], $session['remember']),
            $user,
            $session['sessionId'],
        );
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $this->refreshCookie->read($request);

        if ($token !== null) {
            $this->sessions->revoke($token);
        }

        return $this->json->success($this->refreshCookie->clear($response), [
            'message' => 'Вы вышли из аккаунта',
        ]);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);

        if (!$user instanceof User) {
            throw new LogicException('Auth mw is missing');
        }

        return $this->json->success($response, $this->view->make($user))
            ->withHeader('Cache-Control', 'private, no-store');
    }

    private function signedInResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        User $user,
        bool $remember,
        int $status = 200,
    ): ResponseInterface {
        $oldToken = $this->refreshCookie->read($request);

        if ($oldToken !== null) {
            $this->sessions->revoke($oldToken);
        }

        $userId = $user->getId();

        if ($userId === null) {
            throw new LogicException('Cannot create a session for an unsaved user');
        }

        $session = $this->sessions->issue($userId, $remember);

        return $this->tokenResponse(
            $this->refreshCookie->set($response, $session['token'], $remember),
            $user,
            $session['sessionId'],
            $status,
        );
    }

    private function tokenResponse(ResponseInterface $response, User $user, string $sessionId, int $status = 200): ResponseInterface
    {
        return $this->json->success($response, [
            'access_token' => $this->jwt->create($user, $sessionId),
            'token_type' => 'Bearer',
            'expires_in' => JwtService::ACCESS_TTL,
        ], $status)->withHeader('Cache-Control', 'no-store');
    }

    private function invalidRefreshResponse(ResponseInterface $response): ResponseInterface
    {
        return $this->json->error(
            $this->refreshCookie->clear($response),
            'UNAUTHORIZED',
            'Сессия недействительна или срок её действия истёк',
            401,
        );
    }
}
