<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Webhooks\Jobs\DeliverWebhook;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class DeliverWebhookHandler
{
    public function __construct(
        private WebhookStore $store,
        private WebhookEndpoints $endpoints,
        private WebhookClient $client,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeliverWebhook $job): void
    {
        $result = $this->store->transaction(function () use ($job): ?array {
            $delivery = $this->store->find($job->deliveryId, true);
            if ($delivery === null || $delivery['status'] === 'delivered') {
                return null;
            }
            $start = microtime(true);
            $status = null;
            $error = null;
            try {
                $endpoint = $this->endpoints->get($delivery['endpoint']);
                if ($endpoint === null) {
                    $error = 'endpoint_not_configured';
                } else {
                    $status = $this->client->send($endpoint, $delivery);
                    if ($status < 200 || $status >= 300) {
                        $error = 'http_error';
                    }
                }
            } catch (\InvalidArgumentException) {
                $error = 'invalid_endpoint_configuration';
            } catch (TransportExceptionInterface) {
                $error = 'transport_error';
            }
            $duration = (int) round((microtime(true) - $start) * 1000);
            $this->store->record($delivery, $status, $error, $duration);

            return ['error' => $error, 'http_status' => $status, 'duration_ms' => $duration,
                'retryable' => $error === 'transport_error'
                    || ($error === 'http_error' && ($status >= 500 || in_array($status, [408, 425, 429], true)))];
        });
        if ($result === null) {
            return;
        }

        $this->logger->log($result['error'] === null ? 'info' : 'warning', 'Webhook attempt completed', [
            'delivery_id' => $job->deliveryId, 'http_status' => $result['http_status'],
            'duration_ms' => $result['duration_ms'], 'error' => $result['error'],
        ]);

        if ($result['error'] !== null) {
            $message = 'Webhook delivery failed: ' . $job->deliveryId . ' (' . $result['error'] . ')';
            if (!$result['retryable']) {
                throw new UnrecoverableMessageHandlingException($message);
            }
            throw new \RuntimeException($message);
        }
    }
}
