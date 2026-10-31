<?php

declare(strict_types=1);

namespace App\Entity;

use App\Modules\Account\Enums\UserRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 256, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 20, enumType: UserRole::class, options: ['default' => 'member'])]
    private UserRole $role = UserRole::MEMBER;

    #[ORM\Column(name: 'avatar_path', type: 'string', length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(name: 'password_hash', type: 'string', length: 256)]
    private string $passwordHash;

    #[ORM\Column(name: 'welcome_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $welcomeSentAt = null;

    #[ORM\Column(name: 'email_verified_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(name: 'email_verification_version', type: 'integer', options: ['default' => 0])]
    private int $emailVerificationVersion = 0;

    #[ORM\Column(name: 'email_verification_token_hash', type: 'string', length: 64, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(name: 'email_verification_expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerificationExpiresAt = null;

    #[ORM\Column(name: 'email_verification_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerificationSentAt = null;

    #[ORM\Column(name: 'password_reset_version', type: 'integer', options: ['default' => 0])]
    private int $passwordResetVersion = 0;

    #[ORM\Column(name: 'password_reset_token_hash', type: 'string', length: 64, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(name: 'password_reset_expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

    #[ORM\Column(name: 'password_reset_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetSentAt = null;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $bio = '';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'verification_reminder_queued_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verificationReminderQueuedAt = null;

    #[ORM\Column(name: 'verification_reminder_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verificationReminderSentAt = null;

    public function __construct(string $email, string $passwordHash, ?string $name = null, ?\DateTimeImmutable $createdAt = null)
    {
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->name = $name ?? mb_substr(explode('@', $email)[0], 0, 80);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBio(): string
    {
        return $this->bio;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function updateProfile(string $name, string $bio): void
    {
        $this->name = trim($name);
        $this->bio = trim($bio);
        $this->touch();
    }

    private function touch(): void
    {
        ++$this->version;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getVerificationReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->verificationReminderSentAt;
    }

    public function markVerificationReminderSent(): void
    {
        $this->verificationReminderSentAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $path): void
    {
        $this->avatarPath = $path;
        $this->touch();
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getWelcomeSentAt(): ?\DateTimeImmutable
    {
        return $this->welcomeSentAt;
    }

    public function markWelcomeSent(): void
    {
        $this->welcomeSentAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function getEmailVerificationVersion(): int
    {
        return $this->emailVerificationVersion;
    }

    public function getEmailVerificationSentAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationSentAt;
    }

    public function requestEmailVerification(): int
    {
        $this->emailVerificationTokenHash = null;
        $this->emailVerificationExpiresAt = null;
        $this->emailVerificationSentAt = null;

        return ++$this->emailVerificationVersion;
    }

    public function prepareEmailVerification(string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        $this->emailVerificationTokenHash = $tokenHash;
        $this->emailVerificationExpiresAt = $expiresAt;
    }

    public function markEmailVerificationSent(): void
    {
        $this->emailVerificationSentAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function confirmEmail(string $token, \DateTimeImmutable $now): bool
    {
        if ($this->emailVerifiedAt !== null || $this->emailVerificationTokenHash === null
            || $this->emailVerificationExpiresAt === null || $this->emailVerificationExpiresAt <= $now
            || !hash_equals($this->emailVerificationTokenHash, hash('sha256', $token))) {
            return false;
        }

        $this->emailVerifiedAt = $now;
        $this->touch();
        $this->emailVerificationTokenHash = null;
        $this->emailVerificationExpiresAt = null;

        return true;
    }

    public function requestPasswordReset(): int
    {
        $this->passwordResetTokenHash = null;
        $this->passwordResetExpiresAt = null;
        $this->passwordResetSentAt = null;

        return ++$this->passwordResetVersion;
    }

    public function canSendPasswordReset(int $version): bool
    {
        return $this->passwordResetVersion === $version && $this->passwordResetSentAt === null;
    }

    public function preparePasswordReset(string $hash, \DateTimeImmutable $expiresAt): void
    {
        $this->passwordResetTokenHash = $hash;
        $this->passwordResetExpiresAt = $expiresAt;
    }

    public function markPasswordResetSent(): void
    {
        $this->passwordResetSentAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function resetPassword(string $token, string $passwordHash, \DateTimeImmutable $now): bool
    {
        if ($this->passwordResetTokenHash === null || $this->passwordResetExpiresAt === null
            || $this->passwordResetExpiresAt <= $now
            || !hash_equals($this->passwordResetTokenHash, hash('sha256', $token))) {
            return false;
        }

        $this->passwordHash = $passwordHash;
        $this->requestPasswordReset();
        $this->passwordResetSentAt = $now;

        return true;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): void
    {
        $this->role = $role;
        $this->touch();
    }

    public function getRoles(): array
    {
        return ['ROLE_USER', 'ROLE_' . $this->role->name];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }
}
