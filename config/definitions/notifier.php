<?php

declare(strict_types=1);

use App\Notifications\Channels\DatabaseChannel;
use App\Notifications\Channels\MailChannel;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\NotifierInterface;

return [
    NotifierInterface::class => static fn (DatabaseChannel $database, MailChannel $mail): NotifierInterface => new Notifier([
        'database' => $database,
        'email' => $mail,
    ]),
];
