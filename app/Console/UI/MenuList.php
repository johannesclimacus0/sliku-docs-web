<?php

declare(strict_types=1);

namespace App\Console\UI;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\SelectListWidget;

class MenuList extends SelectListWidget
{
    public string $emptyMessage = 'No records';
    public ?int $visibleRows = null;
    private array $displayItems = [];

    public function setItems(array $items): static
    {
        $this->displayItems = $items;
        return parent::setItems($items);
    }

    public function render(RenderContext $context): array
    {
        if ($this->getSelectedItem() === null) {
            return [AnsiUtils::truncateToWidth($this->emptyMessage, $context->getColumns(), '')];
        }
        if ($this->visibleRows !== null) {
            return $this->renderWindow($context);
        }
        $extra = max(0, AnsiUtils::visibleWidth(DashboardTheme::POINTER) - 1);
        $lines = max(1, $context->getColumns() - $extra)
            |> $context->withColumns(...)
            |> parent::render(...);
        return array_map(static function (string $line) use ($context, $extra): string {
            $replaced = preg_replace_callback(
                '/^((?:\x1b\[[0-?]*[ -\/]*[@-~])*)→ ?/u',
                static fn (array $match): string => $match[1] . DashboardTheme::POINTER . ' ',
                $line,
                1,
                $count
            );
            $text = $count ? $replaced : str_repeat(' ', $extra) . $line;
            return AnsiUtils::truncateToWidth($text, $context->getColumns(), '');
        }, $lines);
    }
    private function renderWindow(RenderContext $context): array
    {
        $rows = max(1, min($this->visibleRows, $context->getRows()));
        $selected = array_search($this->getSelectedItem()['value'], array_column($this->displayItems, 'value'), true);
        $count = count($this->displayItems);
        $size = max(1, $rows - ($count > $rows ? 1 : 0));
        $start = max(0, min($selected - intdiv($size, 2), $count - $size));
        $lines = [];
        foreach (array_slice($this->displayItems, $start, $size, true) as $index => $item) {
            $text = ($index === $selected ? DashboardTheme::POINTER . ' ' : '  ') . $item['label'];
            $text = AnsiUtils::truncateToWidth($text, $context->getColumns(), '');
            $lines[] = $index === $selected ? $this->applyElement('selected', $text) : $text;
        }
        if ($count > $rows && count($lines) < $rows) {
            $lines[] = $this->applyElement('scroll-info', AnsiUtils::truncateToWidth('  (' . ($selected + 1) . '/' . $count . ')', $context->getColumns(), ''));
        }

        return array_slice($lines, 0, $rows);
    }

}
