<?php

declare(strict_types=1);

use App\Health\HealthController;
use App\Modules\Docs\Controllers\DocumentationController;
use Slim\App;

return static function (App $app): void {
    $app->get('/health', HealthController::class . ':ready');
    $app->get('/health/live', HealthController::class . ':live');
    $app->get('/docs/versions', DocumentationController::class . ':versions');
    $app->get('/docs/{version}/navigation', DocumentationController::class . ':navigation');
    $app->get('/docs/{version}/{page:[a-z0-9][a-z0-9-]*}', DocumentationController::class . ':show');
};
