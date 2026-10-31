<?php

declare(strict_types=1);

namespace App\Queue\Jobs;

class WriteLog
{
    public function __construct(public string $message)
    {
    }
}
