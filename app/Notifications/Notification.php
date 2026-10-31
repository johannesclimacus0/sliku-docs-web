<?php

declare(strict_types=1);

namespace App\Notifications;

use Symfony\Component\Notifier\Notification\Notification as SymfonyNotification;

class Notification extends SymfonyNotification
{
    public string $id;

    public function __construct(
        string $subject,
        string $content = '',
        array $channels = ['database'],
        public string $type = 'general',
        public array $data = [],
        ?string $id = null,
    ) {
        parent::__construct($subject, $channels);
        $this->content($content);
        $this->id = $id ?? bin2hex(random_bytes(16));
        if (preg_match('/\A[a-f0-9]{32}\z/', $this->id) !== 1) {
            throw new \InvalidArgumentException('Notification ID must contain 32 lowercase hex characters');
        }
    }
}
