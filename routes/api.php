<?php

declare(strict_types=1);

use App\Broadcast\ConnectionController;
use App\Health\HealthController;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RequireVerifiedEmailMiddleware;
use App\Modules\Account\Controllers\AccountController;
use App\Modules\Account\Controllers\AvatarController;
use App\Modules\Account\Controllers\AvatarMediaController;
use App\Modules\Account\Controllers\DemoController;
use App\Modules\Account\Controllers\NotificationController;
use App\Modules\Account\Controllers\OperationsController;
use App\Modules\Account\Controllers\UserController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\EmailVerificationController;
use App\Modules\Auth\Controllers\PasswordResetController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->group('/health', function (RouteCollectorProxy $routes): void {
        $routes->get('', HealthController::class . ':ready');
        $routes->get('/live', HealthController::class . ':live');
    });

    $app->group('/auth', function (RouteCollectorProxy $routes): void {
        $routes->post('/register', AuthController::class . ':register')->add('rate_limit.register');
        $routes->post('/login', AuthController::class . ':login')->add('rate_limit.login');
        $routes->post('/forgot-password', PasswordResetController::class . ':request')->add('rate_limit.password_forgot');
        $routes->post('/reset-password', PasswordResetController::class . ':reset')->add('rate_limit.password_reset');
        $routes->post('/refresh', AuthController::class . ':refresh');
        $routes->post('/logout', AuthController::class . ':logout');
        $routes->post('/email/verify', EmailVerificationController::class . ':confirm')->add('rate_limit.verification_confirm');

        $routes->group('', function (RouteCollectorProxy $authenticated): void {
            $authenticated->get('/me', AuthController::class . ':me');
            $authenticated->post('/email/verification-notification', EmailVerificationController::class . ':resend')->add('rate_limit.verification_resend');

            $authenticated->group('/avatar', function (RouteCollectorProxy $avatars): void {
                $avatars->post('', AvatarController::class . ':upload')->setName('auth.avatar.upload');
                $avatars->delete('', AvatarController::class . ':delete');
            })->add(RequireVerifiedEmailMiddleware::class);
        })->add(JwtAuthMiddleware::class);
    });

    $app->get('/media/avatars/{filename:[a-f0-9]{32}\.(?:jpg|png|webp)}', AvatarMediaController::class . ':show');

    $app->group('', function (RouteCollectorProxy $authenticated): void {
        $authenticated->group('/broadcast', function (RouteCollectorProxy $routes): void {
            $routes->get('/connection', ConnectionController::class);
        });

        $authenticated->group('/account', function (RouteCollectorProxy $routes): void {
            $routes->get('/overview', AccountController::class . ':overview');
            $routes->get('/operations', OperationsController::class . ':operations')->add(RequireVerifiedEmailMiddleware::class);

            $routes->group('/notifications', function (RouteCollectorProxy $notifications): void {
                $notifications->get('', NotificationController::class . ':index');
                $notifications->get('/unread-count', NotificationController::class . ':count');
                $notifications->patch('/read-all', NotificationController::class . ':readAll');
                $notifications->patch('/{notification:[a-f0-9]{32}}/read', NotificationController::class . ':read');
                $notifications->post('/demo', NotificationController::class . ':demo')->add('rate_limit.demo');
            });

            $routes->group('/demo', function (RouteCollectorProxy $demo): void {
                foreach (['job', 'webhook', 'schedule', 'http'] as $action) {
                    $demo->post('/' . $action, DemoController::class . ':' . $action)->add('rate_limit.demo');
                }
            })->add(RequireVerifiedEmailMiddleware::class);
        });

        $authenticated->group('/users', function (RouteCollectorProxy $routes): void {
            $routes->get('/{user:[0-9]+}', UserController::class . ':show');

            $routes->group('', function (RouteCollectorProxy $verified): void {
                $verified->get('', UserController::class . ':index');
                $verified->patch('/{user:[0-9]+}', UserController::class . ':update');
                $verified->patch('/{user:[0-9]+}/role', UserController::class . ':role');
            })->add(RequireVerifiedEmailMiddleware::class);
        });
    })->add(JwtAuthMiddleware::class);
};
