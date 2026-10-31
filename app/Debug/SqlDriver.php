<?php

declare(strict_types=1);

namespace App\Debug;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

class SqlDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $driver, private Profiler $profiler)
    {
        parent::__construct($driver);
    }

    public function connect(array $params): Connection
    {
        return new SqlConnection(parent::connect($params), $this->profiler);
    }
}
