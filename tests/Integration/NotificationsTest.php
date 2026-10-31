<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Notifications\Handlers\SendNotificationHandler;
use App\Notifications\Jobs\SendNotification;
use App\Notifications\Notification;
use App\Notifications\Notifications;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;

class NotificationsTest extends DatabaseTestCase
{
    public function testQueuedNotificationIsStoredOnceWhenJobIsRepeated(): void
    {
        $user = $this->user();
        $notice = new Notification('A title', 'A message');
        $this->container->get(Notifications::class)->send($user->getId(), $notice);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications'));
        $notice->subject('Mutation after enqueue');
        $handler = $this->container->get(SendNotificationHandler::class);
        $envelopes = iterator_to_array($this->container->get(DoctrineTransport::class)->get());
        self::assertCount(1, $envelopes);
        $job = $envelopes[0]->getMessage();
        self::assertInstanceOf(SendNotification::class, $job);
        self::assertSame($notice->id, $job->notification->id);
        $handler($job);
        $handler($job);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications'));
        $body = $this->json($this->request('GET', '/account/notifications', actor: $user), 200)['data'];
        self::assertSame(1, $body['unreadCount']);
        self::assertSame('A title', $body['items'][0]['title']);
    }

    public function testUserCannotSeeOrMarkAnotherUsersNotification(): void
    {
        $owner = $this->user();
        $other = $this->user('other@example.test');
        $notice = new Notification('Private', 'Owner only');
        $this->container->get(SendNotificationHandler::class)(new SendNotification($owner->getId(), $notice));
        $body = $this->json($this->request('GET', '/account/notifications', actor: $other), 200)['data'];
        self::assertSame([], $body['items']);
        self::assertSame(0, $body['unreadCount']);
        $this->json($this->request('PATCH', '/account/notifications/' . $notice->id . '/read', actor: $other), 404);
        self::assertNull($this->em->getConnection()->fetchOne('SELECT read_at FROM notifications'));
    }

    public function testReadOneIsIdempotentAndReadAllOnlyUpdatesOwnNotifications(): void
    {
        $owner = $this->user();
        $other = $this->user('other@example.test');
        $first = new Notification('First');
        $handler = $this->container->get(SendNotificationHandler::class);
        $handler(new SendNotification($owner->getId(), $first));
        $handler(new SendNotification($owner->getId(), new Notification('Second')));
        $handler(new SendNotification($other->getId(), new Notification('Other')));
        $path = '/account/notifications/' . $first->id . '/read';
        $this->json($this->request('PATCH', $path, actor: $owner), 200);
        $readAt = $this->em->getConnection()->fetchOne('SELECT read_at FROM notifications WHERE id = ?', [$first->id]);
        $this->json($this->request('PATCH', $path, actor: $owner), 200);
        self::assertSame($readAt, $this->em->getConnection()->fetchOne('SELECT read_at FROM notifications WHERE id = ?', [$first->id]));
        $body = $this->json($this->request('GET', '/account/notifications?unreadOnly=1', actor: $owner), 200)['data'];
        self::assertSame(1, $body['pagination']['total']);
        $this->json($this->request('PATCH', '/account/notifications/read-all', actor: $owner), 200);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL'));
    }

    public function testDatabaseAndEmailChannelsUseExistingMailClient(): void
    {
        $user = $this->user();
        $notice = new Notification('Channel test', 'A notification message', ['database', 'email']);
        $this->container->get(SendNotificationHandler::class)(new SendNotification($user->getId(), $notice));
        self::assertCount(1, $this->env->mail->messages);
        $mail = $this->env->mail->messages[0]->getOriginalMessage();
        self::assertSame('Channel test', $mail->getSubject());
        self::assertSame($user->getEmail(), $mail->getTo()[0]->getAddress());
        self::assertStringContainsString('A notification message', $mail->getHtmlBody());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications'));
    }

    public function testSameNotificationCanBeDeliveredToTwoRecipientsWithIndependentReadState(): void
    {
        $first = $this->user();
        $second = $this->user('second@example.test');
        $notice = new Notification('Shared message');
        $handler = $this->container->get(SendNotificationHandler::class);
        $handler(new SendNotification($first->getId(), $notice));
        $handler(new SendNotification($second->getId(), $notice));
        $this->json($this->request('PATCH', '/account/notifications/' . $notice->id . '/read', actor: $first), 200);
        $body = $this->json($this->request('GET', '/account/notifications', actor: $second), 200)['data'];
        self::assertSame(1, $body['unreadCount']);
        self::assertNull($body['items'][0]['readAt']);
    }

    public function testEmailOnlyNotificationDoesNotCreateInboxEntry(): void
    {
        $user = $this->user();
        $notice = new Notification('Email only', '<script>unsafe</script>', ['email']);
        $this->container->get(SendNotificationHandler::class)(new SendNotification($user->getId(), $notice));
        self::assertCount(1, $this->env->mail->messages);
        $html = $this->env->mail->messages[0]->getOriginalMessage()->getHtmlBody();
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications'));
    }

    public function testDeletedRecipientIsSkipped(): void
    {
        $user = $this->user();
        $job = new SendNotification($user->getId(), new Notification('Gone', '', ['database', 'email']));
        $this->em->remove($user);
        $this->em->flush();
        $this->container->get(SendNotificationHandler::class)($job);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notifications'));
        self::assertCount(0, $this->env->mail->messages);
    }

    public function testInboxIsPaginatedAndCountIncludesAllUnreadNotifications(): void
    {
        $user = $this->user();
        $handler = $this->container->get(SendNotificationHandler::class);
        for ($i = 0; $i < 21; $i++) {
            $handler(new SendNotification($user->getId(), new Notification('Notice ' . $i, data: ['index' => $i])));
        }
        $first = $this->json($this->request('GET', '/account/notifications', actor: $user), 200)['data'];
        $second = $this->json($this->request('GET', '/account/notifications?page=2', actor: $user), 200)['data'];
        self::assertCount(20, $first['items']);
        self::assertCount(1, $second['items']);
        self::assertSame(21, $second['pagination']['total']);
        self::assertSame(21, $second['unreadCount']);
        self::assertArrayHasKey('index', $second['items'][0]['data']);
        self::assertNotContains($second['items'][0]['id'], array_column($first['items'], 'id'));
    }

    public function testPaginationValidationAndAuthentication(): void
    {
        $user = $this->user(verified: false);
        $this->json($this->request('GET', '/account/notifications'), 401);
        $this->json($this->request('GET', '/account/notifications?page=0', actor: $user), 422);
        $this->json($this->request('GET', '/account/notifications?unreadOnly=yes', actor: $user), 422);
        $this->json($this->request('GET', '/account/notifications/unread-count', actor: $user), 200);
    }

    public function testDemoNotificationIsQueuedAndRateLimited(): void
    {
        $user = $this->user(verified: false);
        for ($i = 0; $i < 10; $i++) {
            $this->json($this->request('POST', '/account/notifications/demo', actor: $user), 202);
        }
        $this->json($this->request('POST', '/account/notifications/demo', actor: $user), 429);
        self::assertSame(10, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }
}
