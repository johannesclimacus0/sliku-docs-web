<?php

declare(strict_types=1);

namespace App\Modules\Account\Services;

use App\Entity\User;
use App\Modules\Account\Events\UserProfileUpdated;
use App\Modules\Account\Repositories\UserRepository;
use App\Storage\ImageUploadValidator;
use App\Storage\Storage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AvatarService
{
    public function __construct(
        private Storage $storage,
        private ImageUploadValidator $validator,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private EventDispatcherInterface $events,
    ) {
    }

    public function upload(User $user, UploadedFileInterface $file): string
    {
        $image = $this->validator->validate($file);
        $path = 'avatars/' . bin2hex(random_bytes(16)) . '.' . $image['extension'];
        try {
            $this->storage->disk('public')->put($path, $image['contents']);
            $old = $this->replacePath($user, $path);
        } catch (\Throwable $error) {
            $this->deleteFile($path);
            throw $error;
        }
        $this->deleteFile($old);

        return $this->storage->disk('public')->url($path);
    }

    public function delete(User $user): void
    {
        $this->deleteFile($this->replacePath($user, null));
    }

    private function replacePath(User $actor, ?string $path): ?string
    {
        if ($actor->getId() === null) {
            throw new \LogicException('Cannot change avatar for an unsaved user');
        }

        return $this->em->wrapInTransaction(function () use ($actor, $path): ?string {
            $user = $this->users->findForUpdate($actor->getId());
            if ($user === null) {
                throw new \LogicException('Authenticated user no longer exists');
            }
            $old = $user->getAvatarPath();
            $user->setAvatarPath($path);
            $this->events->dispatch(new UserProfileUpdated($user, ['avatar']));
            return $old;
        });
    }

    private function deleteFile(?string $path): void
    {
        if ($path === null) {
            return;
        }
        try {
            $this->storage->disk('public')->delete($path);
        } catch (\Throwable $error) {
            $this->logger->warning('Avatar file cleanup failed', ['path' => $path, 'exception' => $error]);
        }
    }
}
