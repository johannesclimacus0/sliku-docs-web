<?php

declare(strict_types=1);

namespace App\Modules\Account\Jobs;

class RecordUserActivity
{
    public function __construct(
        public int $userId,
        public string $eventId,
        public string $action,
        public bool $fail = false,
    ) {
    }
}
