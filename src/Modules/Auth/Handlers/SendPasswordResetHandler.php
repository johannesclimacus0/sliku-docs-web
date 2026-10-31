<?php

declare(strict_types=1);

namespace App\Modules\Auth\Handlers;

use App\Config\Config;
use App\Mail\MailClient;
use App\Modules\Account\Repositories\UserRepository;
use App\Modules\Auth\Jobs\SendPasswordReset;
use App\Modules\Auth\Mail\PasswordReset;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SendPasswordResetHandler
{
    public function __construct(
        private UserRepository $users,
        private MailClient $mailer,
        private PasswordReset $email,
        private EntityManagerInterface $em,
        private Config $config,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendPasswordReset $job): void
    {
        $sent = $this->em->wrapInTransaction(function () use ($job): bool {
            $user = $this->users->findForUpdate($job->userId);
            if ($user === null || !$user->canSendPasswordReset($job->version)) {
                return false;
            }

            $token = bin2hex(random_bytes(32));
            $expires = new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                ->modify('+' . $this->config->get('password_reset.ttl', 1800) . ' seconds');
            $user->preparePasswordReset(hash('sha256', $token), $expires);
            $this->mailer->send($this->email->create($user, $token, $expires));
            $user->markPasswordResetSent();

            return true;
        });

        $this->logger->info($sent ? 'Password reset sent' : 'Password reset skipped', [
            'user_id' => $job->userId,
            'version' => $job->version,
        ]);
    }
}
