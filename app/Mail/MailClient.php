<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config\Config;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Email;

class MailClient
{
    public function __construct(
        private MailerInterface $mailer,
        private Config $config,
        private BodyRendererInterface $renderer,
    ) {
    }

    public function send(Email $email): void
    {
        $message = clone $email;

        if ($message->getFrom() === []) {
            $message->from(new Address(
                $this->config->get('mail.from_address'),
                $this->config->get('mail.from_name'),
            ));
        }

        $this->renderer->render($message);
        $this->mailer->send($message);
    }

    public function text(string $to, string $subject, string $body): void
    {
        $this->send(new Email()->to($to)->subject($subject)->text($body));
    }
}
