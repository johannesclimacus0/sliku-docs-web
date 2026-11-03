<?php

declare(strict_types=1);

use App\Config\Config;
use App\Modules\Docs\Services\DocumentationVersions;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Parser\MarkdownParser;

return [
    DocumentationVersions::class => static fn (
        Config $config
    ): DocumentationVersions => new DocumentationVersions(
        $config->get('docs.path'),
        $config->get('docs.default_version'),
        $config->get('docs.versions'),
    ),

    MarkdownConverter::class => static function (): MarkdownConverter {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'none',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        return new MarkdownConverter($environment);
    },

    MarkdownParser::class => static fn (
        MarkdownConverter $converter,
    ): MarkdownParser => new MarkdownParser($converter->getEnvironment()),
];
