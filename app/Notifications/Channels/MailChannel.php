<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Mail\MailClient;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class MailChannel implements ChannelInterface
{
    public function __construct(private MailClient $mail)
    {
    }

    public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        if (!$recipient instanceof EmailRecipientInterface) {
            throw new \LogicException('The email channel requires an email recipient');
        }
        $this->mail->send(new TemplatedEmail()->to($recipient->getEmail())
            ->subject($notification->getSubject())
            ->htmlTemplate('emails/notification.html.twig')
            ->context(['title' => $notification->getSubject(), 'body' => $notification->getContent()]));
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return $recipient instanceof EmailRecipientInterface;
    }
}
