<?php

declare(strict_types=1);

namespace App\Console\UI;

class LogBuffer
{
    public const array LEVELS = [
        'ALL' => 0,
        'DEBUG' => 100,
        'INFO' => 200,
        'NOTICE' => 250,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
        'ALERT' => 550,
        'EMERGENCY' => 600
    ];

    public string $level = 'ALL';
    public string $query = '';
    public bool $paused = false;
    public ?array $opened = null;
    private array $entries = [];

    public function ingest(string $raw): void
    {
        if ($this->paused || $this->opened !== null) {
            return;
        }
        $this->entries = [];
        foreach (array_slice(explode("\n", trim($raw)), -100) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $line = ComposeRunner::clean($line);
            $start = strpos($line, '{');
            $data = $start === false ? null : json_decode(substr($line, $start), true);
            if (is_array($data) && is_string($data['message'] ?? null)) {
                $level = strtoupper((string) ($data['level_name'] ?? array_search($data['level'] ?? -1, self::LEVELS, true) ?: 'RAW'));
                $message = ComposeRunner::clean($data['message']);
                $details = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            } else {
                preg_match('/\b(DEBUG|INFO|NOTICE|WARNING|WARN|ERROR|CRITICAL|CRIT|ALERT|EMERGENCY)\b/i', $line, $match);
                $level = strtoupper($match[1] ?? 'RAW');
                $level = match ($level) {
                    'WARN' => 'WARNING', 'CRIT' => 'CRITICAL', default => $level
                };
                $message = $line;
                $details = $line;
            }
            if (!isset(self::LEVELS[$level])) {
                $level = 'RAW';
            }
            $this->entries[] = ['level' => $level, 'message' => $message, 'details' => ComposeRunner::clean($details), 'raw' => $line];
        }
    }

    public function records(): array
    {
        $query = mb_strtolower($this->query);
        return array_values(array_filter($this->entries, fn (array $entry): bool =>
            ($this->level === 'ALL' || (self::LEVELS[$entry['level']] ?? -1) >= self::LEVELS[$this->level]) &&
            ($query === '' || str_contains(mb_strtolower($entry['raw']), $query) || str_contains(mb_strtolower($entry['details']), $query))));
    }

    public function cycleLevel(): void
    {
        $keys = array_keys(self::LEVELS);
        $this->level = $keys[(array_search($this->level, $keys, true) + 1) % count($keys)];
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->opened = null;
        $this->paused = false;
    }
}
