<?php

declare(strict_types=1);

namespace App\Bootstrap;

use RuntimeException;

class ProjectSetup
{
    public function __construct(private string $root)
    {
    }

    public function run(): array
    {
        $path = $this->root . '/.env';
        $created = !file_exists($path);
        if ($created) {
            $contents = file_get_contents($this->root . '/.env.example');
            if ($contents === false || file_put_contents($path, $contents) !== strlen($contents) || !chmod($path, 0600)) {
                throw new RuntimeException('Cannot create .env');
            }
        }
        foreach (['var/docs'] as $directory) {
            $target = $this->root . '/' . $directory;
            if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                throw new RuntimeException('Cannot create ' . $directory);
            }
            if (!is_writable($target)) {
                throw new RuntimeException('Directory is not writable: ' . $directory);
            }
        }

        return ['environment_created' => $created];
    }
}
