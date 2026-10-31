<?php

declare(strict_types=1);

use App\Broadcast\Commands\CheckBroadcastCommand;
use App\Console\UI\FailedJobCommand;
use App\Console\UI\SnapshotCommand;
use App\Modules\Account\Commands\QueueVerificationRemindersCommand;
use App\Modules\Account\Commands\SeedUsersCommand;
use App\Modules\Account\Commands\SetUserRoleCommand;
use App\Modules\Auth\Commands\PruneSessionsCommand;
use App\Queue\Commands\DispatchLogCommand;
use App\Queue\Commands\RetryFailedCommand;
use App\Scheduler\ScheduleRunCommand;
use App\Webhooks\Commands\RetryWebhookCommand;
use App\Webhooks\Commands\WebhookDeliveriesCommand;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\StatsCommand;

return [
    'ui:job' => FailedJobCommand::class,
    'ui:snapshot' => SnapshotCommand::class,
    'broadcast:check' => CheckBroadcastCommand::class,
    'webhooks:deliveries' => WebhookDeliveriesCommand::class,
    'webhooks:retry' => RetryWebhookCommand::class,
    'users:remind' => QueueVerificationRemindersCommand::class,
    'sessions:prune' => PruneSessionsCommand::class,
    'schedule:run' => ScheduleRunCommand::class,
    'db:seed' => SeedUsersCommand::class,
    'user:role' => SetUserRoleCommand::class,
    'queue:dispatch-log' => DispatchLogCommand::class,
    'queue:work' => ConsumeMessagesCommand::class,
    'messenger:consume' => ConsumeMessagesCommand::class,
    'queue:stats' => StatsCommand::class,
    'messenger:stats' => StatsCommand::class,
    'queue:failed' => FailedMessagesShowCommand::class,
    'messenger:failed:show' => FailedMessagesShowCommand::class,
    'queue:forget' => FailedMessagesRemoveCommand::class,
    'messenger:failed:remove' => FailedMessagesRemoveCommand::class,
    'queue:retry' => RetryFailedCommand::class,
    'messenger:failed:retry' => RetryFailedCommand::class,
];
