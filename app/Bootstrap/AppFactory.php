<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Handlers\JsonErrorHandler;
use App\Middleware\CorsMiddleware;
use App\Middleware\RequestLoggingMiddleware;
use App\Middleware\ResponseTimeMiddleware;
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
        $app->addRoutingMiddleware();
        $errors = $app->addErrorMiddleware(false, true, true);
        $errors->setDefaultErrorHandler($container->get(JsonErrorHandler::class));
        $app->add(new ResponseTimeMiddleware());
        $app->add($container->get(CorsMiddleware::class));
        $app->add($container->get(RequestLoggingMiddleware::class));
        (require $root . '/routes/api.php')($app);

        return $app;
    }
}
