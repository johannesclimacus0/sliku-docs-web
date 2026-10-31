<?php

declare(strict_types=1);

use App\Config\Config;
use App\Debug\LogHandler;
use App\Debug\Profiler;
use App\Logging\RequestContext;
use App\Logging\RequestContextProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => static function (Config $config, RequestContext $context, Profiler $profiler): LoggerInterface {
        $handler = new ErrorLogHandler(level: $config->get('logging.level'));
        $handler->setFormatter(new JsonFormatter(appendNewline: false, includeStacktraces: true));

        $logger = new Logger('app');
        $logger->pushHandler($handler);
        if ($profiler->clockwork() !== null) {
            $logger->pushHandler(new LogHandler($profiler));
        }
        $logger->pushProcessor(new RequestContextProcessor($context));
        $logger->pushProcessor(new PsrLogMessageProcessor());

        return $logger;
    },
];
