<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Cache\Cache;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

class Scheduler
{
    public function __construct(
        private array $tasks,
        private Cache $cache,
        private LockFactory $locks,
        private LoggerInterface $logger,
    ) {
    }

    private function key(string $command): string
    {
        return 'schedule_last_' . hash('sha256', $command);
    }

    public function status(): array
    {
        return array_map(fn (array $task): array => $task + ['lastRun' => $this->cache->get($this->key($task['command']))], $this->tasks);
    }

    public function run(callable $execute): array
    {
        $results = [];
        foreach ($this->tasks as $task) {
            $command = $task['command'];
            $lock = $this->locks->createLock('scheduler_' . hash('sha256', $command), 3600);
            if (!$lock->acquire()) {
                $results[] = ['command' => $command, 'status' => 'locked'];
                continue;
            }
            try {
                $last = (int) $this->cache->get($this->key($command), 0);
                if (time() - $last < $task['every']) {
                    $results[] = ['command' => $command, 'status' => 'not_due'];
                    continue;
                }
                $code = $execute($command);
                if ($code !== 0) {
                    throw new \RuntimeException('Scheduled command returned ' . $code);
                }
                if (!$this->cache->set($this->key($command), time(), max(86400, $task['every'] * 2))) {
                    throw new \RuntimeException('Cannot persist scheduler state');
                }
                $results[] = ['command' => $command, 'status' => 'completed'];
                $this->logger->info('Scheduled command completed', ['command' => $command]);
            } catch (\Throwable $error) {
                $results[] = ['command' => $command, 'status' => 'failed'];
                $this->logger->error('Scheduled command failed', ['command' => $command, 'exception' => $error]);
            } finally {
                $lock->release();
            }
        }
        return $results;
    }
}
