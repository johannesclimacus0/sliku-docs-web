<?php

declare(strict_types=1);

namespace App\Modules\Account\Notifications;

use App\Modules\Account\Events\UserEvent;
use App\Notifications\Notification;

class AccountNotification extends Notification
{
    public function __construct(UserEvent $event)
    {
        [$title, $body] = match ($event::NAME) {
            'user.registered' => ['Добро пожаловать', 'Аккаунт создан. Подтвердите email, чтобы открыть все возможности.'],
            'user.verified' => ['Email подтверждён', 'Теперь доступны все возможности вашего аккаунта.'],
            'user.password_reset' => ['Пароль изменён', 'Пароль аккаунта восстановлен.'],
            default => ['Профиль обновлён', 'Изменения профиля успешно сохранены.'],
        };
        parent::__construct($title, $body, type: $event::NAME, data: ['url' => '/profile'], id: $event->id);
    }
}
