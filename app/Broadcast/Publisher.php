<?php

declare(strict_types=1);

namespace App\Broadcast;

use App\Broadcast\Jobs\PublishEvent;
use App\Broadcast\Jobs\PublishNotification;
use App\Config\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Publisher
{
    public function __construct(private HttpClientInterface $http, private Config $config)
    {
    }

    public function publish(PublishEvent|PublishNotification $job): void
    {
        if (!$this->config->get('broadcast.enabled')) {
            return;
        }
        $data = $job instanceof PublishEvent ? $job->data : ['notificationId' => $job->notificationId];
        $this->call('publish', ['channel' => ConnectionToken::channel($job->userId), 'data' => [
            'id' => $job->id, 'event' => $job->event, 'data' => (object) $data,
        ]]);
    }

    public function check(): array
    {
        if (!$this->config->get('broadcast.enabled')) {
            throw new \LogicException('Broadcasting is disabled; set BROADCAST_ENABLED=true');
        }
        return $this->call('info', []);
    }

    private function call(string $method, array $data): array
    {
        $key = $this->config->get('broadcast.api_key');
        $url = $this->config->get('broadcast.api_url');
        if (!is_string($key) || strlen($key) < 32 || !is_string($url)
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \LogicException('Invalid broadcast server API configuration');
        }
        $response = $this->http->request('POST', rtrim($url, '/') . '/api/' . $method, [
            'headers' => ['X-API-Key' => $key],
            'json' => (object) $data,
            'timeout' => 3, 'max_duration' => 5, 'max_redirects' => 0,
        ]);
        $payload = $response->toArray();
        if (isset($payload['error']) || !is_array($payload['result'] ?? null)) {
            throw new \RuntimeException('Broadcast server rejected the ' . $method . ' request');
        }
        return $payload['result'];
    }
}
