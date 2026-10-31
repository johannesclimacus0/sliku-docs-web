<?php

declare(strict_types=1);

namespace App\Health;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Redis;
use RuntimeException;
use Throwable;

class HealthCheck
{
    public function __construct(private ContainerInterface $container, private LoggerInterface $logger)
    {
    }

    /** @return array{status: string, checks: array<string, string>} */
    public function check(): array
    {
        $probes = [
            'database' => fn (): bool => (int) $this->container
                ->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT 1') === 1,
            'redis' => fn (): bool => in_array($this->container->get(Redis::class)->ping(), [true, 'PONG', '+PONG'], true),
        ];
        $checks = [];

        foreach ($probes as $name => $probe) {
            try {
                if (!$probe()) {
                    throw new RuntimeException('Dependency returned an unexpected health check result');
                }

                $checks[$name] = 'up';
            } catch (Throwable $exception) {
                $checks[$name] = 'down';
                $this->logger->error('Health check failed', [
                    'component' => $name,
                    'exception' => $exception,
                ]);
            }
        }

        return [
            'status' => in_array('down', $checks, true) ? 'degraded' : 'ok',
            'checks' => $checks,
        ];
    }
}
