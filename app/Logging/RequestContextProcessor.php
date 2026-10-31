<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestContextProcessor implements ProcessorInterface
{
    public function __construct(private RequestContext $context)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: array_replace($record->extra, $this->context->all()));
    }
}
