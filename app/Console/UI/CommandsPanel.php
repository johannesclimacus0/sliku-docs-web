<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Process\Process;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class CommandsPanel
{
    public ContainerWidget $widget;
    public MenuList $list;
    private TextWidget $heading;
    private TextWidget $output;
    private InputWidget $input;
    private string $stage = 'list';
    private ?string $group = null;
    private ?string $selection = null;
    private string $name = '';
    private ?array $action = null;
    private array $steps = [];
    private string $history = '';
    private int $offset = 0;
    private int $step = 0;
    private string $error = '';
    private string $context = 'host';
    private array $commands = [];
    private int $historyIndex = 0;
    private string $draft = '';

    public function __construct(
        private Tui $tui,
        private ComposeRunner $runner,
        private \Closure $backToMenu,
        private CommandCatalog $catalog = new CommandCatalog()
    ) {
        $this->widget = new ContainerWidget();
        $this->widget->setStyle(new Style(gap: 1));
        $this->heading = new TextWidget('', truncate: true);
        $this->heading->setId('commands-heading');
        $this->list = new MenuList([], maxVisible: 10, keybindings: new Keybindings([
            'select_up' => [Key::UP, 'w'], 'select_down' => [Key::DOWN, 's'],
        ]));
        $this->list->setId('commands-list')->addStyleClass('navigation');
        $this->input = new InputWidget();
        $this->input->setId('commands-input')->setStyle(new Style(hidden: true));
        $this->output = new TextWidget('', truncate: true);
        $this->output->setId('commands-output')->setStyle(new Style(hidden: true));
        foreach ([$this->heading, $this->list, $this->input, $this->output] as $widget) {
            $this->widget->add($widget);
        }
        $this->list->onSelect(fn (SelectEvent $event) => $this->select((string) $event->getValue()));
        $this->list->onCancel(function (): void {
            if ($this->group !== null) {
                $this->group = null;
                $this->updateList();
            } else {
                ($this->backToMenu)();
            }
        });
        $this->input->onSubmit(fn (SubmitEvent $event) => $this->submit($event->getValue()));
        $this->input->onCancel(fn () => $this->back());
        $this->input->onInput($this->historyKey(...));
        $this->updateList();
    }

    public function editing(): bool
    {
        return in_array($this->stage, ['name', 'module', 'command'], true);
    }

    public function busy(): bool
    {
        return $this->runner->busy();
    }

    public function focus(): void
    {
        if ($this->editing()) {
            $this->tui->setFocus($this->input);
        } elseif ($this->stage === 'list') {
            $this->tui->setFocus($this->list);
        } else {
            ($this->backToMenu)();
        }
    }

    public function key(string $key): bool
    {
        $escape = new Keybindings(['back' => [Key::ESCAPE]])->matches($key, 'back');
        if ($this->stage === 'confirm') {
            if ($key === 'y') {
                $this->stage = 'running';
                $this->steps = $this->action['steps'];
                $this->history = '';
                $this->offset = 0;
                $this->step = 0;
                $this->next();
            } elseif ($key === 'n' || $escape) {
                $this->back();
            }
            return true;
        }
        if ($this->stage === 'running') {
            if ($key === 'c') {
                $this->stop();
                $this->stage = 'result';
                $this->history .= "\nCancelled by user. A container-side action may already have completed.\n";
                return true;
            }
            if ($key === 'w' || $key === 's') {
                $this->offset = max(0, $this->offset + ($key === 's' ? 3 : -3));
                return true;
            }
            return false;
        }
        if ($this->stage === 'result') {
            if (($this->action['local'] ?? false) && new Keybindings(['submit' => [Key::ENTER]])->matches($key, 'submit')) {
                $this->beginCommand();
                return true;
            }
            if ($escape) {
                $this->back();
            } elseif ($key === 'w' || $key === 's') {
                $this->offset = max(0, $this->offset + ($key === 's' ? 3 : -3));
            }
            return $escape || in_array($key, ['w', 's'], true);
        }
        if ($key === 'w' || $key === 's') {
            $this->list->handleInput($key);
            return true;
        }
        return false;
    }

    public function interrupt(): bool
    {
        if ($this->stage === 'command') {
            $this->input->setValue('');
            $this->error = '';
            return true;
        }
        if ($this->stage === 'running') {
            return $this->key('c');
        }

        return false;
    }

    public function tick(): void
    {
        $result = $this->runner->poll();
        if ($result === null) {
            return;
        }
        $this->history .= $result['output'] . ($result['error'] === '' ? '' : "\n" . $result['error']);
        $this->history .= "\n[exit " . ($result['exit_code'] ?? ($result['ok'] ? 0 : 'unknown')) . "]\n";
        if ($result['ok'] && $this->steps !== []) {
            $this->next();
        } else {
            $this->steps = [];
            $this->stage = 'result';
            if ($result['ok'] && $this->action['id'] === 'dev:start') {
                $this->history .= "\nVite is ready: http://localhost:5173\nIt will keep running after the dashboard closes.\n";
            }
            $this->offset = 0;
        }
    }

    public function render(int $columns, int $rows): void
    {
        $title = $this->group === 'custom' ? 'shell / ' . $this->context : ($this->group === null ? 'commands' : CommandCatalog::GROUPS[$this->group]);
        $help = match ($this->stage) {
            'command' => 'Enter run |> Up/Down history |> Esc back | non-interactive shell, each command starts in its context directory',
            'name' => 'Class name in PascalCase, e.g. Reports/Monthly |> Enter next |> Esc cancel',
            'module' => 'Module in PascalCase, e.g. Account |> Enter review |> Esc cancel',
            'confirm' => 'Review the command |> y run |> n/Esc cancel',
            'running' => 'Running |> c cancel this task',
            'result' => ($this->action['local'] ?? false) ? 'Enter next command |> Esc back' : 'Task result |> Esc back',
            default => 'Tab focus |> Enter select |> Esc back',
        };
        $this->heading->setText($title . "\n" . ($this->error ?: $help));
        $this->list->setStyle(new Style(hidden: $this->stage !== 'list'));
        $this->input->setStyle(new Style(hidden: !$this->editing()));
        $showOutput = in_array($this->stage, ['confirm', 'running', 'result'], true) || ($this->stage === 'command' && $this->history !== '');
        $this->output->setStyle(new Style(hidden: !$showOutput));
        if ($showOutput) {
            $text = $this->stage === 'confirm' ? $this->preview() : $this->history . $this->runner->output();
            $this->output->setText(DetailText::window($text, $columns, $rows - ($this->stage === 'command' ? 4 : 3), $this->offset));
        }
    }

    public function stop(): void
    {
        $this->history .= $this->runner->output();
        $this->runner->stop();
        $this->steps = [];
    }

    private function select(string $value): void
    {
        $this->error = '';
        if ($this->group === null) {
            $this->group = $value;
            $this->updateList();
            return;
        }
        $this->selection = $value;
        if ($this->group === 'custom') {
            $this->context = substr($value, strlen('command:'));
            $this->beginCommand();
        } elseif ($this->group === 'generators') {
            $this->stage = 'name';
            $this->input->setPrompt('Class name: ')->setValue('');
            $this->input->setStyle(new Style(hidden: false));
            $this->focus();
        } else {
            $this->action = $this->catalog->resolve($value);
            $this->prepare();
        }
    }

    private function submit(string $value): void
    {
        try {
            if ($this->stage === 'command') {
                $this->action = $this->catalog->custom($this->context, $value);
                $this->commands[$this->context][] = trim($value);
                $this->commands[$this->context] = array_slice($this->commands[$this->context], -100);
                $this->prepare();
            } elseif ($this->stage === 'name') {
                $this->name = trim($value);
                $this->catalog->resolve($this->selection, $this->name);
                $this->stage = 'module';
                $this->input->setPrompt('Module: ')->setValue('Account');
            } else {
                $this->action = $this->catalog->resolve($this->selection, $this->name, trim($value));
                $this->prepare();
            }
            $this->error = '';
        } catch (\InvalidArgumentException $error) {
            $this->error = $error->getMessage();
        }
    }

    private function prepare(): void
    {
        $this->stage = 'confirm';
        $this->offset = 0;
        $this->input->setStyle(new Style(hidden: true));
        ($this->backToMenu)();
        if (!$this->action['confirm']) {
            $this->key('y');
        }
    }

    private function next(): void
    {
        $arguments = array_shift($this->steps);
        $this->history .= "\nStep " . ++$this->step . '/' . count($this->action['steps']) . "\n" . $this->commandLine($arguments) . "\n\n";
        try {
            if ($this->action['local'] ?? false) {
                $this->runner->startLocal($arguments);
            } else {
                $this->runner->start($arguments);
            }
        } catch (\Throwable $error) {
            $this->history .= 'Could not start command: ' . get_debug_type($error);
            $this->steps = [];
            $this->stage = 'result';
        }
    }

    private function preview(): string
    {
        $lines = [$this->action['label'], ''];
        foreach ($this->action['steps'] as $arguments) {
            $lines[] = $this->commandLine($arguments);
        }
        if (str_starts_with($this->action['id'], 'php:') && count($this->action['steps']) > 1) {
            $lines[] = "\nThis prepares and clears the isolated slim_test database.";
        }
        return implode("\n", $lines);
    }

    private function commandLine(array $arguments): string
    {
        return new Process(($this->action['local'] ?? false) ? $arguments : ['docker', 'compose', ...$arguments])->getCommandLine();
    }

    private function beginCommand(): void
    {
        $this->stage = 'command';
        $this->error = '';
        $this->historyIndex = count($this->commands[$this->context] ?? []);
        $this->draft = '';
        $this->input->setPrompt($this->context . ' $ ')->setValue('');
        $this->input->setStyle(new Style(hidden: false));
        $this->focus();
    }

    private function historyKey(string $key): bool
    {
        if ($this->stage !== 'command') {
            return false;
        }
        $bindings = new Keybindings(['previous' => [Key::UP], 'next' => [Key::DOWN]]);
        $previous = $bindings->matches($key, 'previous');
        if (!$previous && !$bindings->matches($key, 'next')) {
            return false;
        }
        $history = $this->commands[$this->context] ?? [];
        if ($this->historyIndex === count($history)) {
            $this->draft = $this->input->getValue();
        }
        $this->historyIndex = max(0, min(count($history), $this->historyIndex + ($previous ? -1 : 1)));
        $this->input->setValue($history[$this->historyIndex] ?? $this->draft);

        return true;
    }

    private function back(): void
    {
        $this->stage = 'list';
        $this->error = '';
        $this->offset = 0;
        $this->updateList();
        $this->focus();
    }

    private function updateList(): void
    {
        $labels = $this->group === null ? CommandCatalog::GROUPS : array_map(static fn (array $action): string => $action['label'], $this->catalog->items($this->group));
        $this->list->setItems(array_map(static fn (string $id, string $label): array => ['value' => $id, 'label' => mb_strtolower($label)], array_keys($labels), array_values($labels)));
    }
}
