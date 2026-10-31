<?php

declare(strict_types=1);

namespace App\Modules\Auth\Jobs;

class SendEmailVerification
{
    public function __construct(public int $userId, public int $version)
    {
    }
}
