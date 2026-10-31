<?php

declare(strict_types=1);

namespace App\Debug;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

class SqlMiddleware implements Middleware
{
    public function __construct(private Profiler $profiler)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new SqlDriver($driver, $this->profiler);
    }
}
