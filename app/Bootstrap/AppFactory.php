<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Debug\Profiler;
use App\Debug\ProfilerMiddleware;
use App\Handlers\JsonErrorHandler;
use App\Http\JsonBodyParser;
use App\Middleware\CorsMiddleware;
use App\Middleware\RequestLoggingMiddleware;
use App\Middleware\RequireJsonBodyMiddleware;
use App\Middleware\ResponseStatusMiddleware;
use App\Middleware\ResponseTimeMiddleware;
use App\Routing\RouteEntityBindingStrategy;
use DI\Bridge\Slim\Bridge;
use DI\Container;
use Dotenv\Dotenv;
use Slim\App;

class AppFactory
{
    public static function create(?Container $container = null): App
    {
        $root = dirname(__DIR__, 2);
        Dotenv::createImmutable($root)->safeLoad();

        $container ??= (require $root . '/config/container.php')();
        $app = Bridge::create($container);

        $app->add(new RequireJsonBodyMiddleware());
        $app->addBodyParsingMiddleware(['application/json' => new JsonBodyParser()]);
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler($container->get(JsonErrorHandler::class));
        $app->add(new ResponseStatusMiddleware());
        $app->add(new ResponseTimeMiddleware());
        $profiler = $container->get(Profiler::class);
        if ($profiler->clockwork() !== null) {
            $app->add(new ProfilerMiddleware($app, $profiler));
        }
        $app->add($container->get(CorsMiddleware::class));
        $app->add($container->get(RequestLoggingMiddleware::class));

        $app->getRouteCollector()->setDefaultInvocationStrategy($container->get(RouteEntityBindingStrategy::class));

        (require $root . '/routes/api.php')($app);

        return $app;
    }
}
