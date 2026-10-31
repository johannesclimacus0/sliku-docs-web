<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Account\Events\UserProfileUpdated;
use App\Modules\Account\Events\UserRegistered;
use App\Modules\Account\Handlers\RecordUserActivityHandler;
use App\Modules\Account\Jobs\RecordUserActivity;
use App\Modules\Account\Services\AvatarService;
use App\Modules\Account\Services\ProfileService;
use App\Modules\Account\Services\UserDirectory;
use App\Modules\Auth\Handlers\SendWelcomeEmailHandler;
use App\Modules\Auth\Jobs\SendWelcomeEmail;
use App\Storage\Storage;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BusinessFailureTest extends DatabaseTestCase
{
    public function testRepeatedActivityJobCreatesOnlyOneRecord(): void
    {
        $user = $this->user();
        $job = new RecordUserActivity($user->getId(), bin2hex(random_bytes(16)), 'user.demo');
        $handler = $this->container->get(RecordUserActivityHandler::class);
        $handler($job);
        $handler($job);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user_activity WHERE id = ?', [$job->eventId]));
    }

    public function testRepeatedWelcomeJobDoesNotSendSecondEmail(): void
    {
        $user = $this->user();
        $handler = $this->container->get(SendWelcomeEmailHandler::class);
        $handler(new SendWelcomeEmail($user->getId()));
        $this->em->clear();
        $handler(new SendWelcomeEmail($user->getId()));
        self::assertCount(1, $this->env->mail->messages);
        self::assertNotNull($this->em->getConnection()->fetchOne('SELECT welcome_sent_at FROM users'));
    }

    public function testDirectoryMissesOldCacheAfterCommittedProfileUpdate(): void
    {
        $user = $this->user();
        $directory = $this->container->get(UserDirectory::class);
        self::assertFalse($directory->list(1, '')['cache']['hit']);
        self::assertTrue($directory->list(1, '')['cache']['hit']);
        $this->container->get(ProfileService::class)->update($user, $user, 'Updated name', 'Updated bio');
        $result = $directory->list(1, '');
        self::assertFalse($result['cache']['hit']);
        self::assertSame('Updated name', $result['items'][0]['name']);
        self::assertTrue($directory->list(1, '')['cache']['hit']);
    }

    public function testFailedProfileChangeRollsBackUserAndQueuedEvents(): void
    {
        $user = $this->user();
        $directory = $this->container->get(UserDirectory::class);
        $before = $directory->list(1, '');
        $this->failOn(UserProfileUpdated::class);
        try {
            $this->container->get(ProfileService::class)->update($user, $user, 'Must not persist', '');
            self::fail('Expected simulated failure');
        } catch (RuntimeException $error) {
            self::assertSame('Simulated failure after listeners', $error->getMessage());
        }
        self::assertSame('user', $this->em->getConnection()->fetchOne('SELECT name FROM users'));
        $nextEm = new \Doctrine\ORM\EntityManager($this->em->getConnection(), $this->em->getConfiguration());
        $nextDirectory = new UserDirectory($nextEm, $this->container->get(\App\Cache\Cache::class), $this->container->get(\App\Modules\Account\UserView::class));
        $after = $nextDirectory->list(1, '');
        self::assertTrue($after['cache']['hit']);
        self::assertSame($before['items'], $after['items']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testFailedRegistrationDoesNotLeaveUserOrQueueMessages(): void
    {
        $this->failOn(UserRegistered::class);
        $this->json($this->request('POST', '/auth/register', ['email' => 'new@example.test', 'password' => 'ChangeMe12345!']), 500);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM users'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM refresh_sessions'));
    }

    public function testFailedAvatarSaveRemovesNewFileAndKeepsPreviousAvatar(): void
    {
        $user = $this->user();
        $storage = $this->container->get(Storage::class);
        $old = 'avatars/previous.png';
        $storage->put($old, 'old-image');
        $user->setAvatarPath($old);
        $this->em->flush();
        $this->failOn(UserProfileUpdated::class);
        try {
            $this->container->get(AvatarService::class)->upload($user, $this->image());
            self::fail('Expected simulated failure');
        } catch (RuntimeException $error) {
            self::assertSame('Simulated failure after listeners', $error->getMessage());
        }
        self::assertSame($old, $this->em->getConnection()->fetchOne('SELECT avatar_path FROM users'));
        self::assertSame('old-image', $storage->get($old));
        $files = iterator_to_array($this->container->get(FilesystemOperator::class)->listContents('avatars', true)->filter(static fn ($item): bool => $item->isFile()));
        self::assertCount(1, $files);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testDatabaseConstraintFailureAlsoRemovesUploadedAvatar(): void
    {
        $user = $this->user();
        $this->container->get(EventDispatcherInterface::class)->addListener(UserProfileUpdated::class, function (): void {
            $this->em->getConnection()->insert('refresh_sessions', [
                'token_hash' => str_repeat('f', 64), 'session_id' => str_repeat('f', 32), 'user_id' => 999999,
                'remember_me' => 0, 'expires_at' => time() + 3600,
            ]);
        }, -100);
        try {
            $this->container->get(AvatarService::class)->upload($user, $this->image());
            self::fail('Expected a real foreign key violation');
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException) {
            self::assertNull($this->em->getConnection()->fetchOne('SELECT avatar_path FROM users'));
        }
        self::assertCount(0, iterator_to_array($this->container->get(FilesystemOperator::class)->listContents('', true)->filter(static fn ($item): bool => $item->isFile())));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testSuccessfulAvatarReplacementDeletesOldFileAndDeleteRemovesNewOne(): void
    {
        $user = $this->user();
        $storage = $this->container->get(Storage::class);
        $storage->put('avatars/previous.png', 'old-image');
        $user->setAvatarPath('avatars/previous.png');
        $this->em->flush();
        $service = $this->container->get(AvatarService::class);
        $url = $service->upload($user, $this->image());
        $path = $this->em->getConnection()->fetchOne('SELECT avatar_path FROM users');
        self::assertSame($storage->url($path), $url);
        self::assertFalse($storage->exists('avatars/previous.png'));
        self::assertTrue($storage->exists($path));
        $service->delete($user);
        self::assertNull($this->em->getConnection()->fetchOne('SELECT avatar_path FROM users'));
        self::assertFalse($storage->exists($path));
    }

    private function failOn(string $event): void
    {
        $this->container->get(EventDispatcherInterface::class)->addListener($event, static function (): never {
            throw new RuntimeException('Simulated failure after listeners');
        }, -100);
    }

    private function image(): UploadedFile
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true);
        return new UploadedFile(new StreamFactory()->createStream($contents), 'avatar.png', 'image/png', strlen($contents), UPLOAD_ERR_OK);
    }
}
