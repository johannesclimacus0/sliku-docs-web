<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;

class DashboardTheme
{
    public const string POINTER = '>';
    public const string HEADER = "  . . . %s / little server room . . .\n  [ tiny jobs & occasional screaming ]";
    public const array ARTS = [
        'overview' => 'mliku',
        'containers' => 'mikudayo',
        'queue' => 'mikumiku',
        'schedule' => 'sekai-de',
        'webhooks' => 'leek-spin',
        'logs' => 'popipoipo',
        'commands' => 'miku.exe'
    ];
    public const string MENU_TITLE = '[ room selector ]';
    public const array QUOTES = [
        'default' => [
            'the servers are probably fine',
        ],
        'overview' => [
            'powered by leek and delusion.',
            'certified leek-powered computing.'
        ],
        'containers' => [
            'oo ee oo~',
            'one more docker compose up :3'
        ],
        'queue' => [
            'the worker yearns for the jobs',
            'please stop dispatching things'
        ],
        'schedule' => [
            'zzZ... wake me up at * * * * *',
            'cron is eepy'
        ],
        'webhooks' => [
            'a mysterious creature sent me a payload :3',
            'omg somebody pinged me'
        ],
        'logs' => [
            'the voices are coming from stderr',
            'no errors. suspicious.'
        ],
        'commands' => [
            'casting shell spells :3'
        ],
        'failed' => [
            "(╥﹏╥) miku has made a severe mistake\ncheck the queue and logs",
            "(,,>﹏<,,) it worked on my machine...\ncheck the queue and logs",
            '(｡>﹏<) miku has lost sudo privileges'
        ],
    ];

    public static function styles(): StyleSheet
    {
        return new StyleSheet([
            ':root' => new Style(color: '#d8d0df', gap: 0),
            '.panel' => Style::border([1], 'rounded', '#8b789d')->withPadding([0, 1]),
            '.title' => new Style(color: '#e2b6ce', bold: true),
            '.muted' => new Style(color: '#a59ab0'),
            '.status' => new Style(color: '#dcc79f'),
            '.navigation::selected' => new Style(color: '#f0d4e4', bold: true),
            '.navigation::selected:focus' => new Style(background: '#cfb5da', color: '#1e1926', bold: true),
        ]);
    }

    public static function header(string $name): string
    {
        $name = trim(str_replace(["\n", "\r", "\t"], ' ', ComposeRunner::clean($name)));
        return sprintf(self::HEADER, $name === '' ? 'Application' : mb_substr($name, 0, 120));
    }

    public static function footer(string $screen = 'overview', bool $jobOpen = false): string
    {
        $lines = ['↑↓ scroll menu |> a switch art |> l logs |> q quit'];
        $lines[] = ($screen === 'logs' ? 'Enter details' : 'Enter open') . ' |> r refresh |> Tab focus |> Esc back';
        if ($jobOpen) {
            $lines[] = 't retry |> d delete |> y/n confirm';
        } elseif ($screen === 'commands') {
            $lines[] = 'Enter run/next |> c cancel task |> Esc back';
        } elseif ($screen === 'logs') {
            $lines[] = '/ search |> f level |> p pause |> l source';
        }
        return implode("\n", array_pad($lines, 3, ''));
    }

    public static function caption(string $screen, bool $failed = false): string
    {
        $quotes = self::QUOTES[$failed ? 'failed' : $screen] ?? self::QUOTES['default'];
        return $quotes === [] ? '' : $quotes[array_rand($quotes)];
    }
}
