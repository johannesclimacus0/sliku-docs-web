<?php

declare(strict_types=1);

namespace App\Modules\Account\Handlers;

use App\Mail\MailClient;
use App\Modules\Account\Jobs\SendVerificationReminder;
use App\Modules\Account\Mail\VerificationReminder;
use App\Modules\Account\Repositories\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SendVerificationReminderHandler
{
    public function __construct(
        private UserRepository $users,
        private MailClient $mail,
        private VerificationReminder $template,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }
    public function __invoke(SendVerificationReminder $job): void
    {
        $sent = $this->em->wrapInTransaction(function () use ($job): bool {
            $user = $this->users->findForUpdate($job->userId);
            if ($user === null || $user->getEmailVerifiedAt() !== null || $user->getVerificationReminderSentAt() !== null) {
                return false;
            }
            $this->mail->send($this->template->create($user));
            $user->markVerificationReminderSent();
            return true;
        });
        $this->logger->info('User verification reminder processed', ['user_id' => $job->userId, 'sent' => $sent]);
    }
}
