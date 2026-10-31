<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Entity\User;
use App\Exceptions\ValidationException;
use App\Http\JsonResponse;
use App\Middleware\JwtAuthMiddleware;
use App\Modules\Account\Services\AvatarService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class AvatarController
{
    public function __construct(private AvatarService $avatars, private JsonResponse $json)
    {
    }

    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['avatar'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            throw new ValidationException(['avatar' => ['Выберите файл аватара']]);
        }

        $url = $this->avatars->upload($this->user($request), $file);
        return $this->json->success($response, ['avatarUrl' => $url])->withHeader('Cache-Control', 'no-store');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->avatars->delete($this->user($request));
        return $this->json->success($response, ['avatarUrl' => null])->withHeader('Cache-Control', 'no-store');
    }

    private function user(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        if (!$user instanceof User) {
            throw new \LogicException('Authentication middleware is missing');
        }
        return $user;
    }
}
