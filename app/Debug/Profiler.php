<?php

declare(strict_types=1);

namespace App\Debug;

use App\Config\Config;
use Clockwork\Clockwork;
use Clockwork\Request\Request;
use Clockwork\Storage\FileStorage;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

class Profiler
{
    private ?Clockwork $clockwork = null;
    private bool $active = false;
    private ?TraceableAdapter $cache = null;

    public function __construct(Config $config)
    {
        if ($config->get('app.env') === 'local' && $config->get('app.debug') && class_exists(Clockwork::class)) {
            $this->clockwork = new Clockwork();
            $this->clockwork->storage(
                new FileStorage(
                    dirname(__DIR__, 2) . '/var/clockwork',
                    0700,
                    60
                )
            );
        }
    }

    public function clockwork(): ?Clockwork
    {
        return $this->clockwork;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function begin(): void
    {
        $this->clockwork?->request(new Request());
        $this->cache?->clearCalls();
        $this->active = true;
    }

    public function end(): void
    {
        $this->active = false;
        $this->cache?->clearCalls();
    }

    public function traceCache(TraceableAdapter $cache): void
    {
        $this->cache = $cache;
    }

    public function collectCache(): void
    {
        foreach ($this->cache?->getCalls() ?? [] as $call) {
            foreach (is_array($call->result ?? null) ? $call->result : [] as $key => $result) {
                $type = match ($call->name) {
                    'getItem', 'hasItem', 'getItems' => $result ? 'hit' : 'miss',
                    'save', 'saveDeferred' => 'write',
                    'deleteItem', 'deleteItems', 'clear' => 'delete',
                    default => null,
                };

                if ($type !== null) {
                    $this->clockwork->request()->addCacheQuery(
                        $type,
                        (string) $key,
                        null,
                        (($call->end ?? microtime(true)) - $call->start) * 1000,
                        ['connection' => 'redis', 'time' => $call->start]
                    );
                }
            }
        }
    }

    public function query(string $sql, callable $execute): mixed
    {
        if (!$this->active) {
            return $execute();
        }

        $start = microtime(true);

        try {
            return $execute();
        } finally {
            $this->clockwork->request()->addDatabaseQuery($sql, [], (microtime(true) - $start) * 1000);
        }
    }

    public function log(string $level, string $message): void
    {
        if ($this->active) {
            $this->clockwork->log($level, $message);
        }
    }
}
