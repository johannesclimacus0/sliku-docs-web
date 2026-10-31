<?php

declare(strict_types=1);

namespace Tests\Support;

class ExampleJob
{
    public function __construct(public int $userId)
    {
    }
}
