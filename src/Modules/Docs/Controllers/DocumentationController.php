<?php

declare(strict_types=1);

namespace App\Modules\Docs\Controllers;

use App\Http\JsonResponse;
use App\Modules\Docs\Repositories\DocumentationRepository;
use App\Modules\Docs\Services\DocumentationVersions;
use App\Modules\Docs\Services\MarkdownRenderer;
use App\Modules\Docs\Services\NavigationParser;
use Psr\Http\Message\ResponseInterface;

class DocumentationController
{
    public function __construct(
        private JsonResponse $json,
        private DocumentationRepository $repository,
        private MarkdownRenderer $renderer,
        private NavigationParser $navParser,
        private DocumentationVersions $versions,
    ) {
    }

    public function versions(ResponseInterface $response): ResponseInterface
    {
        return $this->json->success($response, $this->versions->catalog());
    }

    public function navigation(ResponseInterface $response, string $version): ResponseInterface
    {
        $markdown = $this->repository->read($version, 'documentation');
        $sections = $this->navParser->parse($markdown, $version);

        return $this->json->success($response, $sections);
    }

    public function show(ResponseInterface $response, string $version, string $page): ResponseInterface
    {
        $md = $this->repository->read($version, $page);
        $rendered = $this->renderer->render($md, $version);

        return $this->json->success($response, [
            'version' => $version,
            'slug' => $page,
            'title' => $rendered->title,
            'html' => $rendered->html,
            'headings' => $rendered->headings,
        ]);
    }
}
