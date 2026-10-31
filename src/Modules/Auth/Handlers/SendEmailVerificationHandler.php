<?php

declare(strict_types=1);

namespace App\Modules\Auth\Handlers;

use App\Config\Config;
use App\Mail\MailClient;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Auth\Jobs\SendEmailVerification;
use App\Modules\Auth\Mail\EmailVerification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SendEmailVerificationHandler
{
    public function __construct(
        private UserRepository $users,
        private MailClient $mailer,
        private EmailVerification $email,
        private EntityManagerInterface $em,
        private Config $config,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendEmailVerification $job): void
    {
        $sent = $this->em->wrapInTransaction(function () use ($job): bool {
            $user = $this->users->findForUpdate($job->userId);
            if ($user === null || $user->getEmailVerifiedAt() !== null
                || $user->getEmailVerificationVersion() !== $job->version
                || $user->getEmailVerificationSentAt() !== null) {
                return false;
            }

            $token = bin2hex(random_bytes(32));
            $expires = new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                ->modify('+' . $this->config->get('email_verification.ttl', 3600) . ' seconds');
            $user->prepareEmailVerification(hash('sha256', $token), $expires);
            $this->mailer->send($this->email->create($user, $token, $expires));
            $user->markEmailVerificationSent();

            return true;
        });

        $this->logger->info($sent ? 'Email verification sent' : 'Email verification skipped', [
            'user_id' => $job->userId,
            'version' => $job->version,
        ]);
    }
}
