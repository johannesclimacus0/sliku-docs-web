<?php

declare(strict_types=1);

namespace App\Logging;

class RequestContext
{
    private array $context = [];

    public function begin(string $requestId, string $method, string $path, ?string $clientIp): void
    {
        $this->context = [
            'request_id' => $requestId,
            'method' => $method,
            'path' => $path,
            'client_ip' => $clientIp,
        ];
    }

    public function all(): array
    {
        return $this->context;
    }

    public function clear(): void
    {
        $this->context = [];
    }
}
