<?php

declare(strict_types=1);

namespace App\Console\UI;

class DashboardView
{
    public const array SCREENS = [
        'overview' => 'overview',
        'containers' => 'containers',
        'queue' => 'queue',
        'schedule' => 'scheduler',
        'webhooks' => 'webhooks',
        'logs' => 'logs',
        'commands' => 'commands'
    ];

    public function text(string $screen, array $snapshot, string $raw = ''): string
    {
        $lines = [self::SCREENS[$screen], ''];
        if ($screen === 'logs') {
            return trim($raw) === '' ? "Logs\n\nNo records yet." : $raw;
        }
        if ($screen === 'containers') {
            $decoded = json_decode($raw, true);
            $rows = is_array($decoded) && array_is_list($decoded) ? $decoded : $raw
                    |> trim(...)
                    |> (fn ($x) => explode("\n", $x))
                    |> (fn ($x) => array_map(static fn (string $line): mixed => json_decode($line, true), $x, ))
                    |> (fn ($x) => array_filter($x, 'is_array'));
            foreach ($rows as $row) {
                $lines[] = sprintf('%-16s %s', $row['Service'] ?? '?', $row['Status'] ?? $row['State'] ?? '?');
            }
            if ($rows === []) {
                $lines[] = 'No containers. Start them with ./run up -d.';
            }
        } elseif ($screen === 'queue') {
            foreach ($snapshot['queue']['counts'] ?? [] as $name => $count) {
                $lines[] = sprintf('%-16s %d', $name, $count);
            }
            $lines[] = '';
            if (($snapshot['queue']['counts']['failed'] ?? 0) > 0) {
                $lines[] = 'Recent failed jobs below. Tab to select, Enter to inspect.';
            }
        } elseif ($screen === 'schedule') {
            foreach ($snapshot['schedule'] ?? [] as $task) {
                $last = $task['lastRun'] ? gmdate('m-d H:i:s', (int) $task['lastRun']) . ' UTC' : 'not run yet';
                $lines[] = $task['command'];
                $lines[] = '  Every ' . $task['every'] . ' sec. |> ' . $last;
                $lines[] = '';
            }
            $lines[] = 'Scheduled tasks are triggered by cron.';
        } elseif ($screen === 'webhooks') {
            foreach ($snapshot['webhooks'] ?? [] as $row) {
                $lines[] = $row['event_name'] . ' |> ' . $row['status'] . ' |> attempts: ' . $row['attempts'] . ' |> HTTP ' . ($row['last_http_status'] ?? '—');
                $lines[] = '  ' . $row['id'];
            }
            if (($snapshot['webhooks'] ?? []) === []) {
                $lines[] = 'No deliveries yet.';
            }
        } else {
            foreach ($snapshot['health']['checks'] ?? [] as $name => $status) {
                $lines[] = sprintf('%-16s %s', $name, $status === 'up' ? '● up' : '○ down');
            }
            $counts = $snapshot['queue']['counts'] ?? [];
            $lines[] = '';
            $lines[] = 'Queue: ' . ($counts['async'] ?? '—') . ' |> failed: ' . ($counts['failed'] ?? '—');
            $lines[] = 'Scheduled tasks: ' . count($snapshot['schedule'] ?? []);
            $lines[] = '';
            $lines[] = 'Updated: ' . ($snapshot['updatedAt'] ?? 'waiting for data');
        }
        $errors = $snapshot['errors'] ?? [];
        $errors = match ($screen) {
            'queue' => array_intersect_key($errors, ['queue' => true]),
            'schedule' => array_intersect_key($errors, ['schedule' => true]),
            'webhooks' => array_intersect_key($errors, ['webhooks' => true]),
            'overview' => $errors, default => [],
        };
        foreach ($errors as $section => $error) {
            $lines[] = '';
            $lines[] = 'Section unavailable: ' . $section . ': ' . $error;
        }
        return ComposeRunner::clean(implode("\n", $lines));
    }

    public function job(string $id, ?array $job, string $error): string
    {
        if ($error !== '') {
            return "Job #$id\n\n$error\n\nEsc to return to the list";
        }
        if ($job === null) {
            return "Job #$id\n\nLoading job details…";
        }
        $lines = ["Job #$id |> t retry |> d delete |> Esc back", $job['class'], '',
            'Attempts: ' . $job['attempts'] . ' |> retries: ' . $job['retries'],
            'Failed at: ' . ($job['failedAt'] ?? 'time unavailable'), '',
            $job['error']['class'] ?? 'Exception type unavailable',
            $job['error']['message'] ?? 'Exception message unavailable', '', 'Stack trace:'];
        foreach ($job['trace'] as $index => $frame) {
            $lines[] = '#' . $index . ' ' . $frame;
        }
        if ($job['trace'] === []) {
            $lines[] = 'No saved stack trace. Restart the updated worker to capture future failures.';
        }
        return ComposeRunner::clean(implode("\n", $lines));
    }

}
