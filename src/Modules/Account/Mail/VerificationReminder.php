<?php

declare(strict_types=1);

namespace App\Modules\Account\Mail;

use App\Config\Config;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class VerificationReminder
{
    public function __construct(
        private Config $config,
    ) {
    }
    public function create(User $user): TemplatedEmail
    {
        $base = rtrim($this->config->get('email_verification.frontend_url'), '/');
        if (filter_var($base, FILTER_VALIDATE_URL) === false || !in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($base, PHP_URL_QUERY) !== null || parse_url($base, PHP_URL_FRAGMENT) !== null) {
            throw new \LogicException('FRONTEND_URL must be an HTTP URL without query or fragment');
        }
        return new TemplatedEmail()->to($user->getEmail())->subject('Завершите настройку аккаунта')
            ->htmlTemplate('emails/verification-reminder.html.twig')
            ->context(['name' => $user->getName(), 'profileUrl' => $base . '/profile']);
    }
}
