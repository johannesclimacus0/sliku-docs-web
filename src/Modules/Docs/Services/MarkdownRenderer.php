<?php

declare(strict_types=1);

namespace App\Modules\Docs\Services;

use App\Modules\Docs\DTO\RenderedDocumentation;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;

class MarkdownRenderer
{
    public function __construct(
        private MarkdownConverter $converter,
    ) {
    }

    public function render(string $md, string $version): RenderedDocumentation
    {
        $md = str_replace('{{version}}', $version, $md);

        $rendered = $this->converter->convert($md);
        $headings = [];
        $title = null;

        foreach ($rendered->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (!$node instanceof Heading) {
                continue;
            }

            if ($node->getLevel() === 1 && $title === null) {
                $title = StringContainerHelper::getChildText($node, [RawMarkupContainerInterface::class]);
            }

            if (!in_array($node->getLevel(), [2, 3], true)) {
                continue;
            }

            $headings[] = [
                'id' => $node->data->get('attributes/id'),
                'title' => StringContainerHelper::getChildText($node, [RawMarkupContainerInterface::class]),
                'level' => $node->getLevel(),
            ];
        }

        return new RenderedDocumentation($rendered->getContent(), $headings, $title ?: 'Документация');
    }
}
