<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Tui\Ansi\TextWrapper;

class DetailText
{
    public static function window(string $text, int $columns, int $rows, int &$offset): string
    {
        $lines = [];
        foreach (explode("\n", ComposeRunner::clean($text)) as $line) {
            foreach (TextWrapper::wrapLineIntoChunks($line, max(1, $columns)) as $chunk) {
                $lines[] = rtrim($chunk['text']);
            }
        }
        $rows = max(1, $rows);
        $offset = max(0, min($offset, count($lines) - $rows));
        return implode("\n", array_slice($lines, $offset, $rows));
    }
}
