<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Config\Config;

class WebhookEndpoints
{
    public function __construct(private Config $config)
    {
    }

    public function forEvent(string $event): array
    {
        $targets = [];
        foreach ($this->config->get('webhooks.endpoints', []) as $name => $endpoint) {
            if (in_array($event, $endpoint['events'] ?? [], true) && ($endpoint['url'] ?? '') !== '') {
                $targets[$name] = $this->get($name);
            }
        }
        return $targets;
    }

    public function get(string $name): ?array
    {
        $endpoint = $this->config->get('webhooks.endpoints', [])[$name] ?? null;
        if ($endpoint === null || ($endpoint['url'] ?? '') === '') {
            return null;
        }
        $url = $endpoint['url'];
        if (strlen($name) > 100 || preg_match('/\A[a-zA-Z0-9_-]+\z/', $name) !== 1
            || !is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || parse_url($url, PHP_URL_FRAGMENT) !== null
            || !is_string($endpoint['secret'] ?? null) || strlen($endpoint['secret']) < 32) {
            throw new \InvalidArgumentException('Invalid webhook endpoint configuration: ' . $name);
        }
        return $endpoint;
    }
}
