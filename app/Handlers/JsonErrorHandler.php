<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Exceptions\ApiErrorException;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\InvalidInputException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Throwable;

class JsonErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private JsonResponse $json,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        if ($exception instanceof ApiErrorException) {
            $status = match (true) {
                $exception instanceof UnauthorizedException => 401,
                $exception instanceof ForbiddenException => 403,
                $exception instanceof ValidationException => 422,
                $exception instanceof InvalidInputException => 400,
                $exception instanceof NotFoundException => 404,
                $exception instanceof ConflictException => 409,
                default => 500,
            };
            $code = $status < 500 ? $exception->errorCode() : 'INTERNAL_SERVER_ERROR';
            $message = $status < 500 ? $exception->getMessage() : 'Внутренняя ошибка сервера';
        } elseif ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $code = $status < 500 ? 'HTTP_' . $status : 'INTERNAL_SERVER_ERROR';
            $message = match ($status) {
                400 => 'Некорректный запрос',
                401 => 'Необходимо войти в аккаунт',
                403 => 'Недостаточно прав',
                404 => 'Ресурс не найден',
                405 => 'Метод запроса не поддерживается',
                413 => 'Слишком большой запрос',
                415 => 'Неподдерживаемый формат запроса',
                default => $status < 500 ? $exception->getMessage() : 'Внутренняя ошибка сервера',
            };
        } else {
            $status = 500;
            $code = 'INTERNAL_SERVER_ERROR';
            $message = 'Внутренняя ошибка сервера';
        }

        if ($logErrors && $status >= 500) {
            $this->logger->error(
                'Unhandled exception',
                ['status' => $status, 'error_code' => $code]
                    + ($logErrorDetails ? ['exception' => $exception] : []),
            );
        }

        $response = new Response();

        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        return $this->json->error(
            $response,
            $code,
            $message,
            $status,
            $exception instanceof ValidationException ? $exception->details() : null,
        );
    }
}
