<?php

declare(strict_types=1);

return [
    \App\Broadcast\Jobs\PublishEvent::class => \App\Broadcast\Handlers\PublishEventHandler::class,
    \App\Broadcast\Jobs\PublishNotification::class => \App\Broadcast\Handlers\PublishNotificationHandler::class,
    \App\Notifications\Jobs\SendNotification::class => \App\Notifications\Handlers\SendNotificationHandler::class,
    \App\Queue\Jobs\WriteLog::class => \App\Queue\Handlers\WriteLogHandler::class,
    \App\Modules\Auth\Jobs\SendEmailVerification::class => \App\Modules\Auth\Handlers\SendEmailVerificationHandler::class,
    \App\Modules\Auth\Jobs\SendPasswordReset::class => \App\Modules\Auth\Handlers\SendPasswordResetHandler::class,
    \App\Modules\Auth\Jobs\SendWelcomeEmail::class => \App\Modules\Auth\Handlers\SendWelcomeEmailHandler::class,
    \App\Modules\Account\Jobs\RecordUserActivity::class => \App\Modules\Account\Handlers\RecordUserActivityHandler::class,
    \App\Modules\Account\Jobs\SendVerificationReminder::class => \App\Modules\Account\Handlers\SendVerificationReminderHandler::class,
    \App\Webhooks\Jobs\DeliverWebhook::class => \App\Webhooks\DeliverWebhookHandler::class,
];
