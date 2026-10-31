<?php

declare(strict_types=1);

namespace App\Debug;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

class SqlStatement extends AbstractStatementMiddleware
{
    public function __construct(Statement $statement, private string $sql, private Profiler $profiler)
    {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        return $this->profiler->query($this->sql, fn () => parent::execute());
    }
}
