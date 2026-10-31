<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\StampInterface;

class FailureTraceStamp implements StampInterface
{
    public function __construct(public array $frames, public int $retries, public string $failedAt)
    {
    }

    public static function capture(\Throwable $error, int $retries): self
    {
        while ($error instanceof HandlerFailedException && $error->getPrevious() !== null) {
            $error = $error->getPrevious();
        }
        $frames = [$error->getFile() . ':' . $error->getLine()];
        foreach (array_slice($error->getTrace(), 0, 40) as $frame) {
            $frames[] = ($frame['file'] ?? '[internal]') . ':' . ($frame['line'] ?? 0) . ' ' .
                ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '') . '()';
        }
        return new self($frames, $retries, gmdate('c'));
    }
}
