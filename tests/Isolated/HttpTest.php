<?php

declare(strict_types=1);

namespace Tests\Isolated;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Support\TestEnvironment;

class HttpTest extends TestCase
{
    public function testConfiguredResponseIsReturnedWithoutNetwork(): void
    {
        $http = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://example.test/webhook', $url);
            self::assertSame('{"user_id":42}', $options['body']);
            return new MockResponse('{"accepted":true}', ['http_code' => 202]);
        });
        $env = new TestEnvironment(http: $http);
        try {
            $response = $env->get(HttpClientInterface::class)->request('POST', 'https://example.test/webhook', ['json' => ['user_id' => 42]]);
            self::assertSame(202, $response->getStatusCode());
            self::assertSame(['accepted' => true], $response->toArray());
        } finally {
            $env->close();
        }
    }

    public function testUnexpectedHttpCallFailsInsteadOfUsingNetwork(): void
    {
        $env = new TestEnvironment();
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Configure an HTTP response');
            $env->get(HttpClientInterface::class)->request('GET', 'https://example.test');
        } finally {
            $env->close();
        }
    }
}
