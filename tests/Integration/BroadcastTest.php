<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config\Config;
use App\Notifications\Events\NotificationCreated;
use App\Notifications\Notification;
use App\Notifications\NotificationStore;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Support\TestDatabase;

class BroadcastTest extends DatabaseTestCase
{
    private function enable(): void
    {
        $settings = TestDatabase::settings();
        $settings['broadcast']['enabled'] = true;
        $settings['broadcast']['token_key'] = str_repeat('test-token-', 5);
        $this->container->set(Config::class, new Config($settings));
    }

    public function testConnectionEndpointRequiresAuthAndIgnoresUserIdParameter(): void
    {
        $this->enable();
        $user = $this->user();
        $this->json($this->request('GET', '/broadcast/connection'), 401);
        $response = $this->request('GET', '/broadcast/connection?userId=999', actor: $user);
        $body = $this->json($response, 200)['data'];
        self::assertSame('personal:user_' . $user->getId(), $body['channel']);
        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testNotificationQueuesOnePublicationPerRealChange(): void
    {
        $this->enable();
        $user = $this->user();
        $store = $this->container->get(NotificationStore::class);
        $notice = new Notification('One');
        $store->add($user->getId(), $notice);
        $store->add($user->getId(), $notice);
        $store->markRead($user->getId(), $notice->id);
        $store->markRead($user->getId(), $notice->id);
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testFailedTransactionDoesNotPublishNotification(): void
    {
        $this->enable();
        $user = $this->user();
        $store = $this->container->get(NotificationStore::class);
        $this->container->get(EventDispatcherInterface::class)->addListener(NotificationCreated::class, static fn () => throw new \RuntimeException('rollback'), -100);
        try {
            $store->add($user->getId(), new Notification('Rollback'));
            self::fail('Expected failure');
        } catch (\RuntimeException $error) {
            self::assertSame('rollback', $error->getMessage());
        }
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications'));
    }
}
