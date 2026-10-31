<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;

$clientIp = static function (ServerRequestInterface $request): string {
    $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;

    return is_string($address) ? $address : 'unknown';
};

$clientIpAndEmail = static function (ServerRequestInterface $request) use ($clientIp): ?string {
    $email = $request->getParsedBody()->email ?? null;

    if (!is_string($email) || trim($email) === '') {
        return null;
    }

    return $clientIp($request) . "\0" . strtolower(trim($email));
};

return [
    'demo' => [
        'message' => 'Слишком много демонстрационных запросов. Подождите минуту',
        'rules' => [['id' => 'demo_user', 'limit' => 10, 'interval' => '1 minute',
            'key' => static function (ServerRequestInterface $request): ?string {
                $user = $request->getAttribute(\App\Middleware\JwtAuthMiddleware::USER_ATTRIBUTE);
                return $user instanceof \App\Entity\User ? (string) $user->getId() : null;
            }]],
    ],
    'password_forgot' => [
        'message' => 'Слишком много запросов восстановления. Попробуйте позже',
        'rules' => [
            ['id' => 'password_forgot_ip', 'limit' => 10, 'interval' => '1 hour', 'key' => $clientIp],
            ['id' => 'password_forgot_email', 'limit' => 3, 'interval' => '1 hour',
                'key' => static function (ServerRequestInterface $request): ?string {
                    $email = $request->getParsedBody()->email ?? null;
                    return is_string($email) ? strtolower(trim($email)) : null;
                }],
        ],
    ],
    'password_reset' => [
        'message' => 'Слишком много попыток восстановления. Попробуйте позже',
        'rules' => [['id' => 'password_reset_ip', 'limit' => 10, 'interval' => '1 minute', 'key' => $clientIp]],
    ],
    'verification_resend' => [
        'message' => 'Слишком много запросов письма подтверждения. Попробуйте позже',
        'rules' => [[
            'id' => 'email_verification_resend_user',
            'limit' => 3,
            'interval' => '1 hour',
            'key' => static function (ServerRequestInterface $request): ?string {
                $user = $request->getAttribute(\App\Middleware\JwtAuthMiddleware::USER_ATTRIBUTE);

                return $user instanceof \App\Entity\User ? (string) $user->getId() : null;
            },
        ]],
    ],
    'verification_confirm' => [
        'message' => 'Слишком много попыток подтверждения. Попробуйте позже',
        'rules' => [[
            'id' => 'email_verification_confirm_ip',
            'limit' => 20,
            'interval' => '1 minute',
            'key' => $clientIp,
        ]],
    ],
    'login' => [
        'message' => 'Слишком много попыток входа. Попробуйте позже',
        'rules' => [
            [
                'id' => 'login_ip',
                'limit' => 25,
                'interval' => '1 minute',
                'key' => $clientIp,
            ],
            [
                'id' => 'login_ip_email',
                'limit' => 5,
                'interval' => '1 minute',
                'key' => $clientIpAndEmail,
            ],
        ],
    ],
    'register' => [
        'message' => 'Слишком много попыток регистрации. Попробуйте позже',
        'rules' => [
            [
                'id' => 'register_ip',
                'limit' => 10,
                'interval' => '1 minute',
                'key' => $clientIp,
            ],
        ],
    ],
];
