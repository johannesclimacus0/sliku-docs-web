<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Exceptions\EntityNotFoundException;
use App\Http\JsonResponse;
use App\Modules\Account\Input\NotificationListInput;
use App\Modules\Auth\Http\AuthenticatedUser;
use App\Notifications\Notification;
use App\Notifications\Notifications;
use App\Notifications\NotificationStore;
use App\Validation\InputValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class NotificationController
{
    public function __construct(private NotificationStore $store, private Notifications $notifications, private JsonResponse $json, private InputValidator $validator)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $input = new NotificationListInput($query['page'] ?? '1', $query['unreadOnly'] ?? '0');
        $this->validator->validate($input);
        return $this->json->success($response, $this->store->list(AuthenticatedUser::fromRequest($request)->getId(), (int) $input->page, $input->unreadOnly === '1'))
            ->withHeader('Cache-Control', 'private, no-store');
    }

    public function count(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json->success($response, ['unreadCount' => $this->store->unreadCount(AuthenticatedUser::fromRequest($request)->getId())])
            ->withHeader('Cache-Control', 'private, no-store');
    }

    public function read(string $notification, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->store->markRead(AuthenticatedUser::fromRequest($request)->getId(), $notification)) {
            throw new EntityNotFoundException();
        }
        return $this->json->success($response, ['message' => 'Уведомление прочитано'])->withHeader('Cache-Control', 'no-store');
    }

    public function readAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->store->markAllRead(AuthenticatedUser::fromRequest($request)->getId());
        return $this->json->success($response, ['message' => 'Все уведомления прочитаны'])->withHeader('Cache-Control', 'no-store');
    }

    public function demo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = $this->notifications->send(AuthenticatedUser::fromRequest($request)->getId(), new Notification(
            'Проверка уведомлений',
            'Это уведомление доставил Symfony Notifier через очередь.',
            type: 'user.demo',
        ));
        return $this->json->success($response, ['id' => $id, 'message' => 'Уведомление в очереди. Оно появится после обработки воркером.'], 202);
    }
}
