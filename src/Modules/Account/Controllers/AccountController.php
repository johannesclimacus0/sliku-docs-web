<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Http\JsonResponse;
use App\Modules\Account\Repositories\ActivityRepository;
use App\Modules\Account\UserView;
use App\Modules\Auth\Http\AuthenticatedUser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

class AccountController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityRepository $activity,
        private UserView $view,
        private JsonResponse $json,
    ) {
    }

    public function overview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = AuthenticatedUser::fromRequest($request);
        $db = $this->em->getConnection();
        $stats = ['sessions' => (int) $db->fetchOne('SELECT COUNT(*) FROM refresh_sessions WHERE user_id = ? AND expires_at > ?', [$user->getId(), time()]),
            'activities' => (int) $db->fetchOne('SELECT COUNT(*) FROM user_activity WHERE user_id = ?', [$user->getId()]),
            'verified' => $user->getEmailVerifiedAt() !== null, 'avatar' => $user->getAvatarPath() !== null];
        return $this->json->success($response, [
            'user' => $this->view->make($user),
            'stats' => $stats,
            'activity' => $this->activity->forUser($user->getId())
        ])->withHeader('Cache-Control', 'private, no-store');
    }

}
