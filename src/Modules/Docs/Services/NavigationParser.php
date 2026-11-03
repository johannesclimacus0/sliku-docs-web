<?php

declare(strict_types=1);

namespace App\Modules\Docs\Services;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use RuntimeException;

class NavigationParser
{
    public function __construct(
        private MarkdownParser $parser,
    ) {
    }

    /** @return list<array{title: string, items: list<array{title: string, slug: string}>}> */
    public function parse(string $md, string $version): array
    {
        $md = str_replace('{{version}}', $version, $md);

        $document = $this->parser->parse($md);
        $walker = $document->walker();

        $sections = [];
        $currentSection = null;

        while ($event = $walker->next()) {
            if (!$event->isEntering()) {
                continue;
            }
            $node = $event->getNode();
            if ($node instanceof Heading) {
                if ($node->getLevel() !== 2) {
                    throw new RuntimeException('Группа меню должна быть заголовком второго уровня');
                }

                $sections[] = ['title' => $this->plainText($node), 'items' => []];
                $currentSection = array_key_last($sections);
            } elseif ($node instanceof Link) {
                if ($currentSection === null) {
                    throw new RuntimeException('Ссылка меню должна находиться после заголовка группы');
                }

                $pattern = '~\A/docs/' . preg_quote($version, '~') . '/([a-z0-9][a-z0-9-]*)\z~';
                if (preg_match($pattern, $node->getUrl(), $matches) !== 1) {
                    throw new RuntimeException('Некорректный адрес ссылки меню: ' . $node->getUrl());
                }

                $sections[$currentSection]['items'][] = [
                    'title' => $this->plainText($node),
                    'slug' => $matches[1],
                ];
            }
        }

        return $sections;
    }

    private function plainText(Node $node): string
    {
        $text = $node->firstChild();
        if (!$text instanceof Text || $text->next() !== null || trim($text->getLiteral()) === '') {
            throw new RuntimeException('Название в меню должно быть непустым обычным текстом без форматирования');
        }

        return trim($text->getLiteral());
    }
}
