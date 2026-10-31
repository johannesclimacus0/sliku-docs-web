<?php

declare(strict_types=1);

return [
    'transport' => ['table_name' => 'messenger_messages', 'queue_name' => 'async', 'auto_setup' => false],
    'failed_queue' => 'failed',
    'retry' => ['max_retries' => 3, 'delay_ms' => 1000, 'multiplier' => 2, 'max_delay_ms' => 60000],
];
