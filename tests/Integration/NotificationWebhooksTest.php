<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Config\Config;
use App\Notifications\Events\NotificationCreated;
use App\Notifications\Notification;
use App\Notifications\NotificationStore;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Support\TestDatabase;

class NotificationWebhooksTest extends DatabaseTestCase
{
    private function enableWebhooks(): NotificationStore
    {
        $settings = TestDatabase::settings();
        $settings['webhooks']['endpoints'] = ['notifications' => [
            'url' => 'https://receiver.example.test/events', 'secret' => str_repeat('test-secret-', 4),
            'events' => ['notification.created', 'notification.read'],
        ]];
        $this->container->set(Config::class, new Config($settings));
        return $this->container->get(NotificationStore::class);
    }

    public function testCreateAndReadQueueOneWebhookPerTransition(): void
    {
        $store = $this->enableWebhooks();
        $user = $this->user();
        $notice = new Notification('Private title', 'Private body', data: ['secret' => 'private-value']);
        $store->add($user->getId(), $notice);
        $store->add($user->getId(), $notice);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM webhook_deliveries'));
        self::assertTrue($store->markRead($user->getId(), $notice->id));
        self::assertTrue($store->markRead($user->getId(), $notice->id));
        $rows = $this->em->getConnection()->fetchAllAssociative('SELECT event_name, body FROM webhook_deliveries ORDER BY event_name');
        self::assertCount(2, $rows);
        self::assertSame(['notification.created', 'notification.read'], array_column($rows, 'event_name'));
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $payload = json_decode($rows[0]['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($notice->id, $payload['data']['notificationId']);
        self::assertSame($user->getId(), $payload['data']['userId']);
        self::assertStringNotContainsString('private-value', $rows[0]['body']);
        self::assertStringNotContainsString('Private body', $rows[0]['body']);
    }

    public function testReadAllOnlyEmitsForUnreadOwnedNotifications(): void
    {
        $store = $this->enableWebhooks();
        $user = $this->user();
        $other = $this->user('other@example.test');
        $first = new Notification('One');
        $store->add($user->getId(), $first);
        $store->add($user->getId(), new Notification('Two'));
        $store->add($other->getId(), new Notification('Other'));
        $store->markRead($user->getId(), $first->id);
        $store->markAllRead($user->getId());
        $store->markAllRead($user->getId());
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM webhook_deliveries WHERE event_name = 'notification.read'"));
        self::assertSame(1, $store->unreadCount($other->getId()));
    }

    public function testListenerFailureRollsBackNotificationAndDeliveryTogether(): void
    {
        $store = $this->enableWebhooks();
        $user = $this->user();
        $this->container->get(EventDispatcherInterface::class)->addListener(NotificationCreated::class, static function (): never {
            throw new \RuntimeException('Simulated subscriber failure');
        }, -100);
        try {
            $store->add($user->getId(), new Notification('Rollback'));
            self::fail('Expected subscriber failure');
        } catch (\RuntimeException $error) {
            self::assertSame('Simulated subscriber failure', $error->getMessage());
        }
        foreach (['notifications', 'webhook_deliveries', 'messenger_messages'] as $table) {
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table));
        }
    }
}
