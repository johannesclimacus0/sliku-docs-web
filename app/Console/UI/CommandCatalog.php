<?php

declare(strict_types=1);

namespace App\Console\UI;

use App\Console\ClassGenerator;

class CommandCatalog
{
    public const array GROUPS = [
        'custom' => 'run a command',
        'generators' => 'generate classes',
        'backend' => 'backend checks',
        'frontend' => 'frontend checks',
        'database' => 'database',
        'tools' => 'project tools',
        'dev' => 'frontend dev server'
    ];

    public function items(string $group): array
    {
        $actions = [];
        $add = static function (string $id, string $label, array $steps, bool $confirm = false) use (&$actions): void {
            $actions[$id] = compact('id', 'label', 'steps', 'confirm');
        };

        if ($group === 'custom') {
            foreach (['host' => 'Host shell', 'app' => 'PHP container shell', 'frontend' => 'Frontend container shell'] as $context => $label) {
                $add('command:' . $context, $label, []);
            }
        } elseif ($group === 'generators') {
            foreach (ClassGenerator::TYPES as $type => $_) {
                $add('make:' . $type, 'Generate ' . $type, [], true);
            }
        } elseif ($group === 'backend') {
            $add('php:isolated', 'PHP isolated tests', [$this->php(['vendor/bin/phpunit', '--testsuite=Isolated'])]);
            foreach (['integration' => 'Integration', 'all' => 'Isolated,Integration'] as $id => $suite) {
                $add('php:' . $id, 'PHP ' . $id . ' tests (prepare slim_test)', [$this->php(['bin/test-database']), $this->php(['vendor/bin/phpunit', '--testsuite=' . $suite])], true);
            }
            $add('php:style', 'Check PHP formatting', [$this->php(['vendor/bin/php-cs-fixer', 'fix', '--dry-run', '--diff', '--sequential'])]);
            $add('php:format', 'Fix PHP formatting', [$this->php(['vendor/bin/php-cs-fixer', 'fix', '--sequential'])], true);
        } elseif ($group === 'frontend') {
            foreach (['check:lint' => 'Frontend lint', 'type-check' => 'TypeScript check', 'build' => 'Production build'] as $script => $label) {
                $add('npm:' . $script, $label, [$this->npm(['run', $script])]);
            }
            $add('npm:checks', 'All frontend checks', array_map(fn (string $script): array => $this->npm(['run', $script]), ['check:lint', 'build']));
            $add('npm:ci', 'Install frontend dependencies (npm ci)', [$this->npm(['ci'])], true);
        } elseif ($group === 'database') {
            $add('db:status', 'Migration status', [$this->php(['bin/doctrine', 'migrations:status', '--no-ansi', '--no-interaction'])]);
            $add('db:migrate', 'Apply migrations', [$this->php(['bin/doctrine', 'migrations:migrate', '--no-ansi', '--no-interaction'])], true);
            $add('db:diff', 'Generate migration diff', [$this->php(['bin/doctrine', 'migrations:diff', '--no-ansi', '--no-interaction'])], true);
            $add('db:seed', 'Load demo fixtures', [$this->console(['db:seed'])], true);
        } elseif ($group === 'tools') {
            foreach (['list' => 'List all console commands', 'queue:stats' => 'Queue statistics', 'queue:failed' => 'Failed jobs',
                'broadcast:check' => 'Check Centrifugo', 'webhooks:deliveries' => 'Webhook deliveries'] as $command => $label) {
                $add('tool:' . $command, $label, [$this->console([$command])]);
            }
            $add('tool:schedule', 'Run due scheduled tasks', [$this->console(['schedule:run'])], true);
        } elseif ($group === 'dev') {
            $add('dev:start', 'Start Vite (detached)', [['--profile', 'frontend', 'up', '-d', '--wait', '--wait-timeout', '60', 'frontend']]);
            $add('dev:stop', 'Stop Vite', [['--profile', 'frontend', 'stop', 'frontend']]);
            $add('dev:status', 'Vite status', [['ps', '--all', 'frontend']]);
            $add('dev:logs', 'Vite logs', [['logs', '--no-color', '--tail=100', 'frontend']]);
        }
        return $actions;
    }

    public function resolve(string $id, string $name = '', string $module = 'Account'): array
    {
        foreach (array_keys(self::GROUPS) as $group) {
            $action = $this->items($group)[$id] ?? null;
            if ($action === null) {
                continue;
            }
            if ($group === 'generators') {
                if (preg_match('/\A[A-Z][A-Za-z0-9]*\z/', $module) !== 1 || preg_match('/\A[A-Z][A-Za-z0-9]*(?:[\\\\\/][A-Z][A-Za-z0-9]*)*\z/', $name) !== 1) {
                    throw new \InvalidArgumentException('Use PascalCase: Reports/Monthly, module Account. No dots or shell syntax.');
                }
                $action['steps'] = [$this->console([$id, $name, '--module=' . $module])];
            }
            return $action;
        }
        throw new \InvalidArgumentException('Unknown command');
    }

    public function custom(string $context, string $command): array
    {
        $command = trim($command);
        if ($command === '' || strlen($command) > 8192 || str_contains($command, "\0")) {
            throw new \InvalidArgumentException('Enter a command of at most 8192 bytes, without null bytes.');
        }
        $arguments = match ($context) {
            'host' => ['/bin/sh', '-lc', $command],
            'app' => ['docker', 'compose', 'exec', '-T', 'app', 'sh', '-lc', $command],
            'frontend' => ['docker', 'compose', '--profile', 'frontend', 'run', '--rm', '--no-deps', '-T', 'frontend', 'sh', '-lc', $command],
            default => throw new \InvalidArgumentException('Unknown shell context'),
        };

        return ['id' => 'command:' . $context, 'label' => $command, 'steps' => [$arguments], 'confirm' => false, 'local' => true];
    }

    private function php(array $arguments): array
    {
        return ['exec', '-T', 'app', 'php', ...$arguments];
    }

    private function console(array $arguments): array
    {
        return $this->php(['bin/console', ...$arguments, '--no-ansi', '--no-interaction']);
    }

    private function npm(array $arguments): array
    {
        return ['--profile', 'frontend', 'run', '--rm', '--no-deps', '-T', 'frontend', 'npm', ...$arguments];
    }
}
