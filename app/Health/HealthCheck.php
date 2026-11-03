<?php

declare(strict_types=1);

namespace App\Health;

use App\Modules\Docs\Services\DocumentationVersions;
use Psr\Log\LoggerInterface;
use Throwable;

class HealthCheck
{
    public function __construct(private DocumentationVersions $versions, private LoggerInterface $logger)
    {
    }

    public function check(): array
    {
        try {
            $this->versions->catalog();
            return ['status' => 'ok', 'checks' => ['documentation' => 'up']];
        } catch (Throwable $exception) {
            $this->logger->error('Documentation health check failed', ['exception' => $exception]);
            return ['status' => 'degraded', 'checks' => ['documentation' => 'down']];
        }
    }
}
