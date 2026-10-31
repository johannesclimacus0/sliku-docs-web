<?php

declare(strict_types=1);

namespace App\Debug;

use Clockwork\Clockwork;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Support\Slim\ClockworkMiddleware;
use Clockwork\Web\Web;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;

class ProfilerMiddleware extends ClockworkMiddleware
{
    public function __construct(App $app, private Profiler $profiler)
    {
        parent::__construct($app, $profiler->clockwork());
    }

    public function process(Request $request, RequestHandler $handler)
    {
        $path = $request->getUri()->getPath();

        if ($path === '/clockwork') {
            return $this->app->getResponseFactory()->createResponse(302)->withHeader('Location', '/clockwork/');
        }

        if (str_starts_with($path, '/clockwork/')) {
            $assetPath = substr($path, strlen('/clockwork/')) ?: 'index.html';
            if (str_contains($assetPath, '..') || str_contains($assetPath, '\\') || str_contains($assetPath, "\0")) {
                return $this->app->getResponseFactory()->createResponse(404);
            }
            $asset = new Web()->asset($assetPath);
            if ($asset === null || !is_file($asset['path'])) {
                return $this->app->getResponseFactory()->createResponse(404);
            }

            $response = $this->app->getResponseFactory()->createResponse()
                ->withHeader('Content-Type', $asset['mime'])
                ->withHeader('Cache-Control', 'no-store');
            $response->getBody()->write(file_get_contents($asset['path']));

            return $response;
        }

        if (str_starts_with($path, '/__clockwork')) {
            if (preg_match('~^/__clockwork(?:/(?:[0-9-]+|latest)(?:/(?:previous|next)(?:/\d+)?)?)?/?$~D', $path) !== 1
                || $request->getMethod() !== 'GET') {
                return $this->app->getResponseFactory()->createResponse(404);
            }

            return parent::process($request, $handler)->withHeader('Cache-Control', 'no-store');
        }

        $this->startTime = microtime(true);
        $this->profiler->begin();

        try {
            return parent::process($request, $handler);
        } finally {
            $this->profiler->end();
        }
    }

    protected function logRequest(Request $request, $response)
    {
        $this->profiler->collectCache();
        $profile = $this->clockwork->request();
        $profile->memoryUsage = memory_get_peak_usage(true);
        $profile->method = $request->getMethod();
        $profile->uri = $request->getUri()->getPath();
        $profile->time = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? $this->startTime;
        $profile->responseTime = microtime(true);
        $profile->responseStatus = $response->getStatusCode();

        foreach ($request->getHeaders() as $header => $values) {
            if (in_array(strtolower($header), ['accept', 'content-type', 'user-agent', 'x-request-id'], true)) {
                $profile->headers[$header] = $values;
            }
        }

        $this->clockwork->resolveRequest();
        $this->clockwork->storeRequest();

        return $response
            ->withHeader('X-Clockwork-Id', $profile->id)
            ->withHeader('X-Clockwork-Version', Clockwork::VERSION)
            ->withHeader('X-Clockwork-Path', '/__clockwork/')
            ->withHeader('Server-Timing', ServerTiming::fromRequest($profile)->value());
    }
}
