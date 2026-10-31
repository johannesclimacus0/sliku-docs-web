<?php

declare(strict_types=1);

namespace App\Debug;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

class SqlConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private Profiler $profiler)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new SqlStatement(parent::prepare($sql), $sql, $this->profiler);
    }

    public function query(string $sql): Result
    {
        return $this->profiler->query($sql, fn () => parent::query($sql));
    }

    public function exec(string $sql): int|string
    {
        return $this->profiler->query($sql, fn () => parent::exec($sql));
    }
}
