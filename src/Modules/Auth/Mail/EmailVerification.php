<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mail;

use App\Config\Config;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class EmailVerification
{
    public function __construct(private Config $config)
    {
    }

    public function create(User $user, string $token, \DateTimeImmutable $expiresAt): TemplatedEmail
    {
        $base = rtrim($this->config->get('email_verification.frontend_url'), '/');
        if (filter_var($base, FILTER_VALIDATE_URL) === false
            || !in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($base, PHP_URL_QUERY) !== null || parse_url($base, PHP_URL_FRAGMENT) !== null) {
            throw new \LogicException('FRONTEND_URL must be an HTTP URL without query or fragment');
        }

        $url = $base . '/verify-email#' . http_build_query([
            'userId' => $user->getId(),
            'token' => $token,
        ], '', '&', PHP_QUERY_RFC3986);

        return new TemplatedEmail()
            ->to($user->getEmail())
            ->subject('Подтвердите email')
            ->htmlTemplate('emails/verify-email.html.twig')
            ->context([
                'userEmail' => $user->getEmail(),
                'verificationUrl' => $url,
                'expiresAt' => $expiresAt->format(\DateTimeInterface::ATOM),
            ]);
    }
}
