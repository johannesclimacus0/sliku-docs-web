<?php

declare(strict_types=1);

namespace App\Debug;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class LogHandler extends AbstractProcessingHandler
{
    public function __construct(private Profiler $profiler)
    {
        parent::__construct();
    }

    protected function write(LogRecord $record): void
    {
        $this->profiler->log($record->level->getName(), $record->message);
    }
}
