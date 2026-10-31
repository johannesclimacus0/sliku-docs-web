<?php

declare(strict_types=1);

namespace App\Modules\Auth\Handlers;

use App\Mail\MailClient;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Auth\Jobs\SendWelcomeEmail;
use App\Modules\Auth\Mail\WelcomeEmail;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SendWelcomeEmailHandler
{
    public function __construct(
        private UserRepository $users,
        private MailClient $mailer,
        private WelcomeEmail $welcome,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendWelcomeEmail $job): void
    {
        $sent = $this->em->wrapInTransaction(function () use ($job): ?bool {
            $user = $this->users->findForUpdate($job->userId);
            if ($user === null || $user->getEmailVerifiedAt() === null) {
                return null;
            }

            if ($user->getWelcomeSentAt() !== null) {
                return false;
            }

            $this->mailer->send($this->welcome->create($user));
            $user->markWelcomeSent();

            return true;
        });

        if ($sent !== null) {
            $this->logger->info($sent ? 'Welcome email sent' : 'Welcome email already sent, skipping', [
                'user_id' => $job->userId,
                'mail_sent' => $sent,
            ]);
        }
    }
}
