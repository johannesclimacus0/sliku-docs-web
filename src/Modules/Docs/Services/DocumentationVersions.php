<?php

declare(strict_types=1);

namespace App\Modules\Docs\Services;

use App\Modules\Docs\Exceptions\DocumentationNotFoundException;
use InvalidArgumentException;
use RuntimeException;

final readonly class DocumentationVersions
{
    public function __construct(
        private string $path,
        private string $defaultVersion,
        private array $versions,
    ) {
        foreach ($versions as $id => $label) {
            if (preg_match('/\A[a-z0-9]+(?:[.-][a-z0-9]+)*\z/', (string) $id) !== 1 || !is_string($label) || trim($label) === '') {
                throw new InvalidArgumentException('Некорректная версия документации');
            }
        }
        if (!array_key_exists($defaultVersion, $versions)) {
            throw new InvalidArgumentException('Версия документации по умолчанию отсутствует в списке');
        }
    }

    public function catalog(): array
    {
        $available = [];
        foreach ($this->versions as $id => $label) {
            try {
                $this->directory((string) $id);
                $available[] = ['id' => (string) $id, 'label' => $label];
            } catch (DocumentationNotFoundException) {

            }
        }
        if (!in_array($this->defaultVersion, array_column($available, 'id'), true)) {
            throw new RuntimeException('Содержимое версии документации по умолчанию недоступно');
        }

        return ['defaultVersion' => $this->defaultVersion, 'versions' => $available];
    }

    public function directory(string $version): string
    {
        if (!array_key_exists($version, $this->versions)) {
            throw new DocumentationNotFoundException();
        }

        $root = realpath($this->path);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Каталог документации недоступен');
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $directory = realpath($prefix . $version);
        if ($directory === false || !str_starts_with($directory, $prefix) || !is_dir($directory)) {
            throw new DocumentationNotFoundException();
        }
        $menu = realpath($directory . '/documentation.md');
        if ($menu === false || !str_starts_with($menu, $directory . DIRECTORY_SEPARATOR) || !is_file($menu) || !is_readable($menu)) {
            throw new DocumentationNotFoundException();
        }

        return $directory;
    }
}
