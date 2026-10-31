<?php

declare(strict_types=1);

namespace App\Modules\Account\Controllers;

use App\Config\Config;
use App\Entity\User;
use App\Health\HealthCheck;
use App\Http\JsonResponse;
use App\Modules\Account\Security\UserVoter;
use App\Modules\Auth\Http\AuthenticatedUser;
use App\Scheduler\Scheduler;
use App\Security\Authorization;
use App\Webhooks\WebhookStore;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

class OperationsController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Authorization $authorization,
        private Scheduler $scheduler,
        private WebhookStore $webhooks,
        private HealthCheck $health,
        private JsonResponse $json,
        private Config $config,
    ) {
    }

    public function operations(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->authorization->denyAccessUnlessGranted(AuthenticatedUser::fromRequest($request), UserVoter::MANAGE, User::class);
        $db = $this->em->getConnection();
        return $this->json->success($response, [
            'health' => $this->health->check(),
            'counts' => ['users' => (int) $db->fetchOne('SELECT COUNT(*) FROM users'),
                'queued' => (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'async'"),
                'failed' => (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'"),
                'webhooks' => (int) $db->fetchOne('SELECT COUNT(*) FROM webhook_deliveries')],
            'schedule' => $this->scheduler->status(), 'deliveries' => $this->webhooks->recent(),
            'webhookEnabled' => ($this->config->get('webhooks.endpoints.users.url', '') !== ''),
        ])->withHeader('Cache-Control', 'private, no-store');
    }
}
