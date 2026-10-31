<?php

declare(strict_types=1);

namespace App\Modules\Account\Jobs;

class SendVerificationReminder
{
    public function __construct(
        public int $userId,
    ) {
    }
}
