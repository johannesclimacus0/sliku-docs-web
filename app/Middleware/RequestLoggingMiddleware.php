<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Logging\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class RequestLoggingMiddleware implements MiddlewareInterface
{
    public const REQUEST_ID_ATTRIBUTE = 'request_id';

    public function __construct(private LoggerInterface $logger, private RequestContext $context)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine('X-Request-ID');

        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/', $requestId) !== 1) {
            $requestId = bin2hex(random_bytes(16));
        }

        $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $this->context->begin(
            $requestId,
            $request->getMethod(),
            $request->getUri()->getPath(),
            is_string($address) ? $address : null,
        );

        $start = hrtime(true);

        try {
            $response = $handler->handle($request->withAttribute(self::REQUEST_ID_ATTRIBUTE, $requestId));
            $status = $response->getStatusCode();
            $level = match (true) {
                $status >= 500 => LogLevel::ERROR,
                $status >= 400 => LogLevel::WARNING,
                default => LogLevel::INFO,
            };

            $this->logger->log($level, 'HTTP request completed', [
                'status' => $status,
                'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ]);

            return $response->withHeader('X-Request-ID', $requestId);
        } catch (Throwable $exception) {
            $this->logger->error('HTTP request failed before a response was produced', [
                'exception' => $exception,
                'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ]);

            throw $exception;
        } finally {
            $this->context->clear();
        }
    }
}
