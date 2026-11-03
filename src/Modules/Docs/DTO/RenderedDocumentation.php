<?php

declare(strict_types=1);

namespace App\Modules\Docs\DTO;

final readonly class RenderedDocumentation
{
    /** @param list<array{id: string, title: string, level: 2|3}> $headings */
    public function __construct(
        public string $html,
        public array $headings,
        public string $title,
    ) {
    }
}
