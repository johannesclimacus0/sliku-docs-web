<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponse;
use App\RateLimit\RateLimitRule;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Symfony\Component\RateLimiter\RateLimit;

class RateLimitMiddleware implements MiddlewareInterface
{
    /** @param list<RateLimitRule> $rules */
    public function __construct(
        private array $rules,
        private string $message,
        private JsonResponse $json,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        foreach ($this->rules as $rule) {
            $limit = $rule->consume($request);

            if ($limit !== null && !$limit->isAccepted()) {
                return $this->tooManyRequests($limit);
            }
        }

        return $handler->handle($request);
    }

    private function tooManyRequests(RateLimit $limit): ResponseInterface
    {
        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        return $this->json
            ->error(new Response(), 'TOO_MANY_REQUESTS', $this->message, 429)
            ->withHeader('Retry-After', (string) $retryAfter);
    }
}
