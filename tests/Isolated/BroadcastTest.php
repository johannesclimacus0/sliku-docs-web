<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Broadcast\Broadcast;
use App\Broadcast\Commands\CheckBroadcastCommand;
use App\Broadcast\ConnectionToken;
use App\Broadcast\Jobs\PublishEvent;
use App\Broadcast\Jobs\PublishNotification;
use App\Broadcast\Listeners\NotificationBroadcastSubscriber;
use App\Broadcast\Publisher;
use App\Config\Config;
use App\Notifications\Events\NotificationCreated;
use App\Notifications\Events\NotificationRead;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Support\TestEnvironment;

class BroadcastTest extends TestCase
{
    private function config(): Config
    {
        return new Config(['broadcast' => ['enabled' => true, 'token_key' => str_repeat('token-', 8),
            'api_key' => str_repeat('api-', 10), 'api_url' => 'http://centrifugo:8000',
            'websocket_url' => 'ws://localhost:8000/connection/websocket']]);
    }

    public function testTokenOnlyGrantsCurrentUserChannelAndExpires(): void
    {
        $config = $this->config();
        $result = new ConnectionToken($config)->issue(42);
        $claims = JWT::decode($result['token'], new Key($config->get('broadcast.token_key'), 'HS256'));
        self::assertSame('42', $claims->sub);
        self::assertSame(['personal:user_42'], $claims->channels);
        self::assertSame(300, $claims->exp - $claims->iat);
        self::assertArrayNotHasKey('api_key', $result);
    }

    public function testDisabledBroadcastDoesNotIssueToken(): void
    {
        self::assertSame(['enabled' => false], new ConnectionToken(new Config(['broadcast' => ['enabled' => false]]))->issue(42));
    }

    public function testPublisherUsesPrivateChannelAndServerSideKey(): void
    {
        $config = $this->config();
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($config): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://centrifugo:8000/api/publish', $url);
            self::assertContains('X-API-Key: ' . $config->get('broadcast.api_key'), $options['headers']);
            $body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('personal:user_42', $body['channel']);
            self::assertSame('notification.created', $body['data']['event']);
            self::assertArrayNotHasKey('token', $body['data']);
            return new MockResponse('{"result":{}}');
        });
        new Publisher($http, $config)->publish(new PublishNotification('event', 42, 'notification.created', 'notice'));
    }

    public function testApiErrorWithHttp200StillFailsPublication(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":{"code":102,"message":"unknown channel"}}'));
        $this->expectException(\RuntimeException::class);
        new Publisher($http, $this->config())->publish(new PublishNotification('event', 42, 'notification.read', 'notice'));
    }

    public function testCustomEventIsQueuedWithRecipientAndPayload(): void
    {
        $env = new TestEnvironment(settings: ['broadcast' => ['enabled' => true]]);
        try {
            $id = $env->get(Broadcast::class)->toUser(42, 'report.ready', ['reportId' => 7]);
            $job = $env->transport->getSent()[0]->getMessage();
            self::assertInstanceOf(PublishEvent::class, $job);
            self::assertSame($id, $job->id);
            self::assertSame(42, $job->userId);
            self::assertSame('report.ready', $job->event);
            self::assertSame(['reportId' => 7], $job->data);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $id);
        } finally {
            $env->close();
        }
    }

    public function testDisabledBroadcastDoesNotQueueEvent(): void
    {
        $env = new TestEnvironment(settings: ['broadcast' => ['enabled' => false]]);
        try {
            self::assertNull($env->get(Broadcast::class)->toUser(42, 'report.ready'));
            self::assertCount(0, $env->transport->getSent());
        } finally {
            $env->close();
        }
    }

    public function testNotificationEventsUseTheGenericQueueJob(): void
    {
        $env = new TestEnvironment(settings: ['broadcast' => ['enabled' => true]]);
        try {
            $subscriber = $env->get(NotificationBroadcastSubscriber::class);
            $subscriber->changed(new NotificationCreated('notice', 42, 'demo'));
            $subscriber->changed(new NotificationRead('notice', 42, 'demo', time()));
            $jobs = $env->transport->getSent();
            self::assertCount(2, $jobs);
            self::assertInstanceOf(PublishEvent::class, $jobs[0]->getMessage());
            self::assertSame('notification.created', $jobs[0]->getMessage()->event);
            self::assertSame('notification.read', $jobs[1]->getMessage()->event);
            self::assertSame(['notificationId' => 'notice'], $jobs[0]->getMessage()->data);
        } finally {
            $env->close();
        }
    }

    public function testCustomPayloadReachesPublisher(): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('personal:user_42', $body['channel']);
            self::assertSame(['id' => 'event', 'event' => 'report.ready', 'data' => ['reportId' => 7]], $body['data']);
            return new MockResponse('{"result":{}}');
        });
        new Publisher($http, $this->config())->publish(new PublishEvent('event', 42, 'report.ready', ['reportId' => 7]));
    }

    public function testDiagnosticChecksApiWithAnObjectBody(): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://centrifugo:8000/api/info', $url);
            self::assertSame('{}', $options['body']);
            return new MockResponse('{"result":{"nodes":[{"uid":"test"}]}}');
        });
        $command = new CommandTester(new CheckBroadcastCommand(new Publisher($http, $this->config())));
        self::assertSame(0, $command->execute([]));
        self::assertStringContainsString('Nodes: 1', $command->getDisplay());
    }

    public function testDiagnosticReportsFailureWithoutPrintingServerResponse(): void
    {
        $http = new MockHttpClient(new MockResponse('{"error":{"message":"secret-response"}}'));
        $command = new CommandTester(new CheckBroadcastCommand(new Publisher($http, $this->config())));
        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('check failed', $command->getDisplay());
        self::assertStringNotContainsString('secret-response', $command->getDisplay());
    }
}
