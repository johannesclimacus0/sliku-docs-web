<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Entity\User;
use App\Mail\MailClient;
use App\Modules\Auth\Mail\WelcomeEmail;
use Symfony\Component\Mime\Email;
use Tests\Support\IsolatedTestCase;

class MailTest extends IsolatedTestCase
{
    public function testTextEmailIsCapturedWithDefaultSender(): void
    {
        $this->env->get(MailClient::class)->text('user@example.test', 'Test subject', 'Hello');
        self::assertCount(1, $this->env->mail->messages);
        $message = $this->env->mail->messages[0]->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('user@example.test', $message->getTo()[0]->getAddress());
        self::assertSame('sender@example.test', $message->getFrom()[0]->getAddress());
        self::assertSame('Test subject', $message->getSubject());
        self::assertSame('Hello', $message->getTextBody());
    }

    public function testWelcomeTemplateIsRenderedBeforeSending(): void
    {
        $email = new WelcomeEmail()->create(new User('user@example.test', 'unused-hash'));
        $this->env->get(MailClient::class)->send($email);
        $message = $this->env->mail->messages[0]->getOriginalMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertStringContainsString('user@example.test', $message->getHtmlBody());
        self::assertStringContainsString('Test Workspace', $message->getHtmlBody());
        self::assertStringContainsString('user@example.test', $message->getTextBody());
        self::assertSame([], $email->getFrom());
    }
}
