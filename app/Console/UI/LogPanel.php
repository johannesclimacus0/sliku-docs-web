<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class LogPanel
{
    public ContainerWidget $widget;
    public MenuList $list;
    public LogBuffer $buffer;
    private TextWidget $heading;
    private TextWidget $detail;
    private InputWidget $search;
    private bool $editing = false;
    private int $offset = 0;
    private string $itemsKey = '';

    public function __construct(private Tui $tui, private \Closure $backToMenu)
    {
        $this->buffer = new LogBuffer();
        $this->widget = new ContainerWidget();
        $this->widget->expandVertically(true);
        $this->widget->setStyle(new Style(gap: 1));
        $this->heading = new TextWidget('', truncate: true);
        $this->heading->setId('log-heading');
        $this->search = new InputWidget();
        $this->search->setId('log-search')->setStyle(new Style(hidden: true));
        $this->search->setPrompt('Search: ');
        $this->list = new MenuList([], maxVisible: 10, keybindings: new Keybindings([
            'select_up' => [Key::UP, 'w'], 'select_down' => [Key::DOWN, 's'],
        ]));
        $this->list->setId('log-list')->addStyleClass('navigation');
        $this->list->emptyMessage = 'No records match the filters.';
        $this->detail = new TextWidget('', truncate: true);
        $this->detail->setId('log-detail')->setStyle(new Style(hidden: true));
        foreach ([$this->heading, $this->search, $this->list, $this->detail] as $widget) {
            $this->widget->add($widget);
        }
        $this->list->onSelect(function (SelectEvent $event): void {
            $this->buffer->opened = $this->buffer->records()[(int) $event->getValue()] ?? null;
            $this->offset = 0;
            ($this->backToMenu)();
        });
        $this->list->onCancel($backToMenu);
        $this->search->onSubmit(function (SubmitEvent $event): void {
            $this->buffer->query = mb_substr($event->getValue(), 0, 200);
            $this->finishSearch();
        });
        $this->search->onCancel(fn () => $this->finishSearch());
    }

    public function editing(): bool
    {
        return $this->editing;
    }

    public function frozen(): bool
    {
        return $this->buffer->paused || $this->buffer->opened !== null || $this->editing;
    }

    public function accept(string $raw): void
    {
        if (!$this->editing) {
            $this->buffer->ingest($raw);
        }
    }

    public function focus(): void
    {
        $this->tui->setFocus($this->list);
    }

    public function key(string $key): bool
    {
        $escape = new Keybindings(['back' => [Key::ESCAPE]])->matches($key, 'back');
        if ($this->buffer->opened !== null) {
            if ($escape) {
                $this->buffer->opened = null;
                $this->offset = 0;
                $this->focus();
            } elseif ($key === 'w' || $key === 's') {
                $this->offset = max(0, $this->offset + ($key === 's' ? 3 : -3));
            }
            return $escape || in_array($key, ['w', 's', 'p', 'f', '/'], true);
        }
        if ($key === 'w' || $key === 's') {
            $this->list->handleInput($key);
            return true;
        }
        if ($key === '/') {
            $this->editing = true;
            $this->search->setValue($this->buffer->query)->setStyle(new Style(hidden: false));
            $this->tui->setFocus($this->search);
        } elseif ($key === 'f') {
            $this->buffer->cycleLevel();
        } elseif ($key === 'p') {
            $this->buffer->paused = !$this->buffer->paused;
        } else {
            return false;
        }
        return true;
    }

    public function render(string $source, int $columns, int $rows): void
    {
        $records = $this->buffer->records();
        $this->list->visibleRows = max(1, $rows - ($this->editing ? 5 : 3));
        $state = $this->buffer->opened !== null ? 'RECORD' : ($this->buffer->paused ? 'PAUSED' : 'LIVE');
        $query = $this->buffer->query === '' ? '—' : $this->buffer->query;
        $help = $this->buffer->opened !== null ? 'Esc back' : 'Tab list |> / search |> f level |> p pause |> Enter details';
        $this->heading->setText("Logs $source [$state] |> {$this->buffer->level} |> search: $query |> " . count($records) . "\n" . $help);
        $opened = $this->buffer->opened;
        $this->list->setStyle(new Style(hidden: $opened !== null));
        $this->detail->setStyle(new Style(hidden: $opened === null));
        if ($opened !== null) {
            $this->detail->setText(DetailText::window($opened['details'], $columns, $rows - 2, $this->offset));
        } else {
            $items = array_map(static fn (array $record, int $index): array => [
                'value' => (string) $index, 'label' => '[' . $record['level'] . '] ' . str_replace("\n", ' ', $record['message']),
            ], $records, array_keys($records));
            $key = hash('sha256', serialize($items));
            if ($key !== $this->itemsKey) {
                $this->itemsKey = $key;
                $this->list->setItems($items);
                $this->list->setSelectedIndex(max(0, count($items) - 1));
            }
        }
    }

    private function finishSearch(): void
    {
        $this->editing = false;
        $this->search->setStyle(new Style(hidden: true));
        $this->focus();
    }
}
