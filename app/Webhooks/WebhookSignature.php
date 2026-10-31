<?php

declare(strict_types=1);

namespace App\Webhooks;

class WebhookSignature
{
    public function sign(string $deliveryId, string $body, int $timestamp, string $secret): string
    {
        return 'v1=' . hash_hmac('sha256', $deliveryId . '.' . $timestamp . '.' . $body, $secret);
    }

    public function verify(string $deliveryId, string $body, string $timestamp, string $signature, string $secret, int $tolerance = 300): bool
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $deliveryId) !== 1
            || preg_match('/\A[0-9]{1,12}\z/', $timestamp) !== 1
            || (string) (int) $timestamp !== $timestamp
            || preg_match('/\Av1=[a-f0-9]{64}\z/', $signature) !== 1
            || $secret === '' || abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }
        return hash_equals($this->sign($deliveryId, $body, (int) $timestamp, $secret), $signature);
    }
}
