<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\Terminal;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class Dashboard
{
    private string $screen = 'overview';
    private string $kind = '';
    private string $raw = '';
    private string $notice = '';
    private float $noticeUntil = 0;
    private string $logService = 'app';
    private array $snapshot = [];
    private float $refreshed = 0;
    private int $offset = 0;
    private ?array $confirmation = null;
    private ?string $jobId = null;
    private ?array $job = null;
    private string $jobError = '';
    private LogPanel $logs;
    private CommandsPanel $commands;
    private bool $logFocused = false;
    private bool $queueFocused = false;
    private Tui $tui;
    private SelectListWidget $menu;
    private SelectListWidget $failed;
    private TextWidget $content;
    private TextWidget $status;
    private ContainerWidget $body;
    private ContainerWidget $sidebar;
    private TextWidget $miku;
    private TextWidget $sidebarLabel;
    private TextWidget $mikuNote;
    private TextWidget $footer;
    private Miku $art;
    private ?string $artOverride = null;
    private array $captions = [];
    private bool $connectionFailed = false;

    public function __construct(
        private ComposeRunner $runner,
        private DashboardView $view = new DashboardView(),
        private string $appName = 'Application',
        private ?ComposeRunner $taskRunner = null
    ) {
    }

    public function create(?TerminalInterface $terminal = null): Tui
    {
        $this->art = new Miku();
        $this->selectCaptions();
        $this->tui = new Tui(DashboardTheme::styles(), terminal: $terminal ?? new Terminal());
        $title = new TextWidget(DashboardTheme::header($this->appName), truncate: true);
        $title->addStyleClass('title');
        $this->tui->add($title);

        $this->menu = new MenuList(array_map(
            static fn (string $value, string $label): array => compact('value', 'label'),
            array_keys(DashboardView::SCREENS),
            array_values(DashboardView::SCREENS),
        ), maxVisible: count(DashboardView::SCREENS));
        $this->menu->addStyleClass('navigation');
        $this->sidebar = new ContainerWidget();
        $this->sidebar->expandVertically(true);
        $this->sidebar->addStyleClass('panel');
        $this->sidebar->setStyle(new Style(gap: 1, maxColumns: 30, flex: 0));
        $this->sidebarLabel = new TextWidget(DashboardTheme::MENU_TITLE, truncate: true);
        $this->sidebarLabel->addStyleClass('muted');
        $this->sidebar->add($this->sidebarLabel);
        $this->sidebar->add($this->menu);
        $this->miku = new TextWidget('', truncate: true);
        $this->miku->addStyleClass('miku');
        $this->miku->setId('miku');
        $this->sidebar->add($this->miku);
        $this->mikuNote = new TextWidget('', truncate: true);
        $this->mikuNote->setId('miku-note');
        $this->mikuNote->addStyleClass('muted');
        $this->sidebar->add($this->mikuNote);
        $this->content = new TextWidget('Loading…', truncate: true);
        $this->content->setId('dashboard-content');
        $this->failed = new MenuList([], maxVisible: 8, keybindings: new Keybindings([
            'select_up' => [Key::UP, 'w'], 'select_down' => [Key::DOWN, 's'],
        ]));
        $this->failed->emptyMessage = 'No failed jobs.';
        $this->logs = new LogPanel($this->tui, function (): void {
            $this->logFocused = false;
            $this->tui->setFocus($this->menu);
        });
        $this->commands = new CommandsPanel(
            $this->tui,
            $this->taskRunner ?? new ComposeRunner(dirname(__DIR__, 3), timeout: 600),
            function (): void {
                $this->tui->setFocus($this->menu);
            }
        );
        $this->body = new ContainerWidget();
        $this->body->expandVertically(true);
        $this->body->addStyleClass('panel');
        $this->body->setStyle(new Style(gap: 1, flex: 1));
        $this->body->add($this->content);
        $this->failed->setStyle(new Style(hidden: true));
        $this->logs->widget->setStyle(new Style(gap: 1, hidden: true));
        $this->body->add($this->failed);
        $this->body->add($this->logs->widget);
        $this->commands->widget->setStyle(new Style(gap: 1, hidden: true));
        $this->body->add($this->commands->widget);
        $panels = new ContainerWidget();
        $panels->setStyle(new Style(direction: Direction::Horizontal, gap: 1));
        $panels->expandVertically(true);
        $panels->add($this->sidebar);
        $panels->add($this->body);
        $this->tui->add($panels);
        $this->status = new TextWidget('', truncate: true);
        $this->status->setId('dashboard-status');
        $this->status->addStyleClass('status');
        $this->tui->add($this->status);
        $this->footer = new TextWidget('', truncate: true);
        $this->footer->setId('dashboard-footer');
        $this->footer->addStyleClass('muted');
        $this->tui->add($this->footer);
        $this->tui->setFocus($this->menu);
        $this->menu->onSelect(fn (SelectEvent $event) => $this->open((string) $event->getValue()));
        $this->menu->onCancel(fn () => $this->tui->stop());
        $this->failed->onCancel(function (): void {
            $this->queueFocused = false;
            $this->confirmation = null;
            $this->tui->setFocus($this->menu);
        });
        $this->failed->onSelect(function (SelectEvent $event): void {
            if (!$this->runner->busy() && $this->screen === 'queue') {
                $this->openJob((string) $event->getValue());
            }
        });
        $this->tui->addListener($this->handleInput(...));
        $this->tui->onTick(function (): void {
            $this->commands->tick();
            $result = $this->runner->poll();
            if ($result !== null) {
                $this->receive($result);
            }
            if (!$this->runner->busy() && $this->confirmation === null && $this->jobId === null && $this->screen !== 'commands' && !($this->screen === 'logs' && $this->logs->frozen()) && microtime(true) - $this->refreshed > 5) {
                $this->refresh();
            }
            $this->decorate();
            $height = max(3, $this->tui->getTerminal()->getRows() - ($this->screen === 'queue' && $this->jobId === null ? 18 : 10));
            $columns = max(8, $this->tui->getTerminal()->getColumns() - $this->sidebarWidth() - 8);
            if ($this->screen === 'commands') {
                $this->commands->render($columns, $height);
            } elseif ($this->screen === 'logs') {
                $this->logs->render($this->logService, $columns, $height + 2);
            } elseif ($this->jobId !== null) {
                $text = $this->view->job($this->jobId, $this->job, $this->jobError);
                $this->content->setText(DetailText::window($text, $columns, $height, $this->offset));
            } else {
                $text = $this->view->text($this->screen, $this->snapshot, $this->raw);
                $lines = explode("\n", $text);
                $this->offset = min($this->offset, max(0, count($lines) - $height));
                array_slice($lines, $this->offset, $height)
                    |> (fn ($x) => implode("\n", $x))
                    |> $this->content->setText(...);
            }
            $idle = $this->commands->busy()
                ? 'Task running in commands'
                : (
                    $this->screen === 'logs' && $this->logs->frozen()
                    ? '(paused) log updates are frozen'
                    : '(ok) refresh every 5 sec.'
                );
            $this->status->setText($this->notice ?: ($this->runner->busy() ? '( . . ) checking the wires…' : $idle));
        });
        $this->decorate();
        return $this->tui;
    }

    public function run(): void
    {
        $this->create();
        try {
            $this->refresh();
            $this->tui->run();
        } finally {
            $this->runner->stop();
            $this->commands->stop();
        }
    }

    private function selectCaptions(): void
    {
        $this->captions = [
            'normal' => DashboardTheme::caption($this->screen),
            'failed' => DashboardTheme::caption($this->screen, true),
        ];
    }

    private function decorate(): void
    {
        $terminal = $this->tui->getTerminal();
        $width = $this->sidebarWidth();
        $this->sidebar->setStyle(new Style(gap: 1, maxColumns: $width, flex: 0));
        $this->sidebarLabel->setText(str_pad(DashboardTheme::MENU_TITLE, $width - 4));
        $sections = match ($this->screen) {
            'queue' => ['queue'], 'schedule' => ['schedule'], 'webhooks' => ['webhooks'],
            'overview' => array_keys($this->snapshot['errors'] ?? []), default => [],
        };
        $unhappy = $this->connectionFailed || array_intersect($sections, array_keys($this->snapshot['errors'] ?? [])) !== []
            || (in_array($this->screen, ['overview', 'queue'], true) && ($this->snapshot['queue']['counts']['failed'] ?? 0) > 0)
            || ($this->screen === 'overview' && ($this->snapshot['health']['status'] ?? 'ok') !== 'ok');
        $artName = $this->artOverride ?? DashboardTheme::ARTS[$this->screen];
        $picture = $terminal->getColumns() < 70 ? '' : $this->art->colored($artName, $width - 4, $terminal->getRows() - $this->artHeightReserve());
        $this->miku->setText($picture);
        $this->miku->setStyle(new Style(hidden: $picture === ''));
        $this->mikuNote->setText($this->captions[$unhappy ? 'failed' : 'normal']);
        $this->footer->setText(DashboardTheme::footer($this->screen, $this->jobId !== null));
    }

    private function handleInput(InputEvent $event): void
    {
        $key = $event->getData();
        $keys = new Keybindings(['quit' => ['q', 'ctrl+c'], 'interrupt' => ['ctrl+c'], 'back' => [Key::ESCAPE], 'tab' => [Key::TAB]]);
        $typing = ($this->screen === 'logs' && $this->logs->editing()) || ($this->screen === 'commands' && $this->commands->editing());

        if ($typing && !$keys->matches($key, 'interrupt')) {
            return;
        }

        $key = strtr($key, [
            'й' => 'q', 'ц' => 'w', 'ы' => 's', 'ф' => 'a', 'д' => 'l',
            'к' => 'r', 'е' => 't', 'в' => 'd', 'н' => 'y', 'т' => 'n',
            'с' => 'c', 'а' => 'f', 'з' => 'p',
            'Й' => 'q', 'Ц' => 'w', 'Ы' => 's', 'Ф' => 'a', 'Д' => 'l',
            'К' => 'r', 'Е' => 't', 'В' => 'd', 'Н' => 'y', 'Т' => 'n',
            'С' => 'c', 'А' => 'f', 'З' => 'p',
        ]);

        if ($keys->matches($key, 'interrupt') && $this->screen === 'commands' && $this->commands->interrupt()) {
            $event->stopPropagation();
            return;
        }

        if ($keys->matches($key, 'quit')) {
            $event->stopPropagation();
            $this->tui->stop();
            return;
        }

        if ($this->confirmation !== null) {
            $event->stopPropagation();
            if ($key === 'y') {
                ['action' => $action, 'id' => $id] = $this->confirmation;
                $this->confirmation = null;
                $this->notice = 'Running command…';
                $arguments = $action === 'retry' ? ['queue:retry', $id] : ['queue:forget', $id, '--transport=failed', '--force'];
                $this->start($action, $this->console($arguments));
            } elseif ($key === 'n' || $keys->matches($key, 'back')) {
                $this->confirmation = null;
                $this->notice = 'Action cancelled';
                $this->noticeUntil = microtime(true) + 3;
            }
            return;
        }

        if ($this->screen === 'commands' && $this->commands->key($key)) {
            $event->stopPropagation();
            return;
        }

        if ($this->screen === 'logs' && $this->logs->key($key)) {
            $event->stopPropagation();
            return;
        }

        if ($this->jobId !== null && $keys->matches($key, 'back')) {
            $event->stopPropagation();
            if (!$this->mutating()) {
                $this->runner->stop();
                $this->closeJob();
                $this->refresh();
            }
        } elseif ($this->jobId !== null && in_array($key, ['t', 'd'], true)) {
            $event->stopPropagation();
            if (!$this->runner->busy()) {
                $this->confirmation = ['id' => $this->jobId, 'action' => $key === 't' ? 'retry' : 'delete'];
                $this->notice = ($key === 't' ? 'Retry' : 'Permanently delete') . ' job #' . $this->jobId . '? y yes, n cancel';
            }
        } elseif ($key === 'r') {
            $event->stopPropagation();
            $this->refresh();
        } elseif ($keys->matches($key, 'tab') && $this->screen === 'queue' && $this->jobId === null) {
            $event->stopPropagation();
            $this->queueFocused = !$this->queueFocused;
            $this->tui->setFocus($this->queueFocused ? $this->failed : $this->menu);
        } elseif ($keys->matches($key, 'tab') && $this->screen === 'commands') {
            $event->stopPropagation();
            if ($this->tui->getFocus() === $this->menu) {
                $this->commands->focus();
            } else {
                $this->tui->setFocus($this->menu);
            }
        } elseif ($keys->matches($key, 'tab') && $this->screen === 'logs') {
            $event->stopPropagation();
            $this->logFocused = $this->tui->getFocus() !== $this->logs->list;
            $this->tui->setFocus($this->logFocused ? $this->logs->list : $this->menu);
        } elseif ($key === 'w' || $key === 's') {
            $event->stopPropagation();
            if ($this->screen === 'queue' && $this->queueFocused && $this->jobId === null) {
                $this->failed->handleInput($key);
            } else {
                $this->offset = max(0, $this->offset + ($key === 's' ? 3 : -3));
            }

        } elseif ($key === 'a') {
            $event->stopPropagation();
            $names = $this->art->names();
            $current = $this->artOverride ?? DashboardTheme::ARTS[$this->screen];
            $next = $names[(array_search($current, $names, true) + 1) % count($names)];
            $this->artOverride = $next === DashboardTheme::ARTS[$this->screen] ? null : $next;
        } elseif ($key === 'l' && $this->screen !== 'logs') {
            $event->stopPropagation();
            $this->open('logs');
        } elseif ($key === 'l' && $this->screen === 'logs') {
            $event->stopPropagation();
            $sources = ['app', 'worker', 'frontend'];
            $this->logService = $sources[(array_search($this->logService, $sources, true) + 1) % count($sources)];
            $this->logs->buffer->clear();
            $this->refresh();
        }
    }

    private function artHeightReserve(): int
    {
        return 14 + count(DashboardView::SCREENS);
    }

    private function sidebarWidth(): int
    {
        $terminal = $this->tui->getTerminal();
        $budget = min(48, max(24, (int) floor($terminal->getColumns() * 0.34)));
        return max(24, $this->art->maxWidth($budget - 4, $terminal->getRows() - $this->artHeightReserve()) + 4);
    }

    private function mutating(): bool
    {
        return in_array($this->kind, ['retry', 'delete'], true) && $this->runner->busy();
    }

    private function console(array $arguments): array
    {
        return ['exec', '-T', 'app', 'php', 'bin/console', ...$arguments, '--no-ansi', '--no-interaction'];
    }

    private function openJob(string $id): void
    {
        $this->jobId = $id;
        $this->job = null;
        $this->jobError = '';
        $this->offset = 0;
        $this->failed->setStyle(new Style(hidden: true));
        $this->queueFocused = false;
        $this->tui->setFocus($this->menu);
        $this->refresh();
    }

    private function closeJob(): void
    {
        $this->jobId = null;
        $this->job = null;
        $this->jobError = '';
        $this->offset = 0;
        $this->failed->setStyle(new Style(hidden: false));
        $this->queueFocused = true;
        $this->tui->setFocus($this->failed);
    }

    private function open(string $screen): void
    {
        if ($this->mutating()) {
            return;
        }
        if ($this->screen === 'queue' && $this->jobId === null) {
            $this->failed->setStyle(new Style(hidden: true));
        }
        if ($this->screen === 'commands') {
            $this->commands->widget->setStyle(new Style(gap: 1, hidden: true));
            $this->content->setStyle(new Style(hidden: false));
        }
        if ($this->screen === 'logs') {
            $this->logs->widget->setStyle(new Style(gap: 1, hidden: true));
            $this->content->setStyle(new Style(hidden: false));
        }
        $this->screen = $screen;
        $this->selectCaptions();
        $this->menu->setSelectedIndex((int) array_search($screen, array_keys(DashboardView::SCREENS), true));
        $this->artOverride = null;
        $this->raw = '';
        $this->offset = 0;
        $this->notice = '';
        $this->noticeUntil = 0;
        $this->confirmation = null;
        $this->jobId = null;
        $this->job = null;
        $this->jobError = '';
        $this->logFocused = false;
        $this->queueFocused = false;
        if ($screen === 'queue') {
            $this->failed->setStyle(new Style(hidden: false));
        }
        if ($screen === 'commands') {
            $this->content->setStyle(new Style(hidden: true));
            $this->commands->widget->setStyle(new Style(gap: 1, hidden: false));
        }
        if ($screen === 'logs') {
            $this->content->setStyle(new Style(hidden: true));
            $this->logs->widget->setStyle(new Style(gap: 1, hidden: false));
        }
        $this->refresh();
    }

    private function refresh(): void
    {
        if ($this->screen === 'commands') {
            $this->runner->stop();
            return;
        }
        if ($this->mutating()) {
            return;
        }
        if ($this->jobId !== null) {
            $this->start('job', $this->console(['ui:job', $this->jobId]));
        } elseif ($this->screen === 'containers') {
            $this->start('containers', ['ps', '--all', '--format', 'json']);
        } elseif ($this->screen === 'logs') {
            if ($this->logs->frozen()) {
                return;
            }
            $this->start('logs', ['logs', '--no-color', '--tail=100', '--timestamps', $this->logService]);
        } else {
            $this->start('snapshot', $this->console(['ui:snapshot']));
        }
    }

    private function start(string $kind, array $arguments): void
    {
        $this->kind = $kind;
        $this->refreshed = microtime(true);
        try {
            $this->runner->start($arguments);
        } catch (\Throwable $error) {
            $this->connectionFailed = true;
            $this->notice = 'Could not start Docker Compose: ' . get_debug_type($error);
        }
    }

    private function receive(array $result): void
    {
        if (!$result['ok']) {
            if ($this->kind === 'job') {
                $data = json_decode($result['output'], true);
                $this->jobError = ComposeRunner::clean($data['error'] ?? $result['error'] ?: 'Could not read job details');
            }
            $this->connectionFailed = true;
            $this->notice = $result['error'] ?: $result['output'] ?: 'Command failed';
            $this->notice = mb_substr(str_replace("\n", ' ', $this->notice), 0, 220);
            return;
        }
        if ($this->kind === 'snapshot') {
            $data = json_decode($result['output'], true);
            if (!is_array($data) || !isset($data['updatedAt'], $data['errors'])) {
                $this->notice = 'Invalid ui:snapshot response. Check ./run logs app.';
                return;
            }
            $this->connectionFailed = false;
            $this->snapshot = $data;
            if (!$this->queueFocused && $this->confirmation === null) {
                $this->failed->setItems(array_map(static fn (array $row): array => [
                    'value' => (string) $row['id'], 'label' => '#' . $row['id'] . ' |> ' . $row['created_at'],
                ], $data['queue']['failed'] ?? []));
            }
        } elseif ($this->kind === 'job') {
            $data = json_decode($result['output'], true);
            if (!is_array($data) || !isset($data['id'], $data['class']) || (string) $data['id'] !== $this->jobId) {
                $this->jobError = 'Invalid ui:job response';
                return;
            }
            $this->job = $data;
            $this->jobError = '';
        } elseif (in_array($this->kind, ['retry', 'delete'], true)) {
            $this->notice = trim($result['output']);
            $this->noticeUntil = microtime(true) + 6;
            $this->closeJob();
            $this->queueFocused = false;
            $this->tui->setFocus($this->menu);
            $this->refreshed = 0;
        } elseif ($this->kind === 'logs') {
            $this->connectionFailed = false;
            $this->logs->accept($result['output']);
        } else {
            $this->connectionFailed = false;
            $this->raw = $result['output'];
        }
        if ($this->confirmation === null && microtime(true) >= $this->noticeUntil) {
            $this->notice = '';
        }
    }
}
