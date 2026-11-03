<?php

declare(strict_types=1);

namespace App\Modules\Docs\Repositories;

use App\Modules\Docs\Exceptions\DocumentationNotFoundException;
use App\Modules\Docs\Services\DocumentationVersions;
use RuntimeException;

final readonly class DocumentationRepository
{
    public function __construct(
        private DocumentationVersions $versions,
    ) {
    }

    public function read(string $version, string $page): string
    {
        if (preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $page) !== 1) {
            throw new DocumentationNotFoundException();
        }

        $root = $this->versions->directory($version);

        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $file = realpath($prefix . $page . '.md');
        if ($file === false || !str_starts_with($file, $prefix) || !is_file($file)) {
            throw new DocumentationNotFoundException();
        }

        if (!is_readable($file)) {
            throw new RuntimeException('Не удалось прочитать статью документации');
        }

        $markdown = file_get_contents($file);
        if ($markdown === false) {
            throw new RuntimeException('Не удалось прочитать статью документации');
        }

        return $markdown;
    }
}
