<?php

declare(strict_types=1);

namespace App\Webhooks\Jobs;

class DeliverWebhook
{
    public function __construct(public string $deliveryId)
    {
    }
}
