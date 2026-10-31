<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Process\Process;

class ComposeRunner
{
    private ?Process $process = null;

    public function __construct(
        private string $root,
        private array $prefix = ['docker', 'compose'],
        private float $timeout = 12
    ) {
    }

    public function start(array $arguments): void
    {
        $this->launch([...$this->prefix, ...$arguments], $this->timeout);
    }

    public function startLocal(array $arguments): void
    {
        $this->launch($arguments, null);
    }

    private function launch(array $arguments, ?float $timeout): void
    {
        $this->stop();
        $this->process = new Process($arguments, $this->root, timeout: $timeout);
        $this->process->start();
    }

    public function poll(): ?array
    {
        if ($this->process === null) {
            return null;
        }
        try {
            $this->process->checkTimeout();
            $this->limitOutput();
            if ($this->process->isRunning()) {
                return null;
            }
            $this->limitOutput();
            $result = ['ok' => $this->process->isSuccessful(),
                'output' => self::clean($this->process->getOutput()),
                'error' => self::clean($this->process->getErrorOutput()),
                'exit_code' => $this->process->getExitCode()];
        } catch (\Throwable $error) {
            $result = ['ok' => false, 'output' => $this->output(), 'error' => 'Command failed: ' . $error->getMessage(), 'exit_code' => null];
        }
        $this->stop();
        return $result;
    }

    public function busy(): bool
    {
        return $this->process !== null;
    }

    public function output(): string
    {
        return $this->process === null ? '' : self::clean($this->process->getOutput() . $this->process->getErrorOutput());
    }

    public function stop(): void
    {
        if ($this->process !== null) {
            $this->process->stop(0.2);
            $this->process = null;
        }
    }

    private function limitOutput(): void
    {
        if (strlen($this->process->getOutput()) > 262144 || strlen($this->process->getErrorOutput()) > 262144) {
            $this->process->stop(0);
            throw new \RuntimeException('Command output exceeded the dashboard limit');
        }
    }

    public static function clean(string $value): string
    {
        $value = preg_replace('/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)/', '', $value) ?? '';
        $value = preg_replace('/\x1b\[[0-?]*[ -\/]*[@-~]/', '', $value) ?? '';
        return preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', '', $value) ?? '';
    }
}
