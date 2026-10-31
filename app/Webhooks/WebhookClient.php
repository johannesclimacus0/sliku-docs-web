<?php

declare(strict_types=1);

namespace App\Webhooks;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookClient
{
    public function __construct(private HttpClientInterface $http, private WebhookSignature $signature)
    {
    }

    public function send(array $endpoint, array $delivery): int
    {
        $timestamp = time();
        $response = $this->http->request('POST', $endpoint['url'], [
            'body' => $delivery['body'],
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Id' => $delivery['id'],
                'X-Webhook-Event' => $delivery['event_name'],
                'X-Webhook-Timestamp' => (string) $timestamp,
                'X-Webhook-Signature' => $this->signature->sign($delivery['id'], $delivery['body'], $timestamp, $endpoint['secret']),
            ],
            'timeout' => 5, 'max_duration' => 10, 'max_redirects' => 0,
            'buffer' => false,
        ]);
        try {
            return $response->getStatusCode();
        } finally {
            $response->cancel();
        }
    }
}
