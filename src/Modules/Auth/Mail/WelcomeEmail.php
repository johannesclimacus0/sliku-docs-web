<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mail;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class WelcomeEmail
{
    public function create(User $user): TemplatedEmail
    {
        return new TemplatedEmail()
            ->to($user->getEmail())
            ->subject('Добро пожаловать!')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context(['userEmail' => $user->getEmail()]);
    }
}
