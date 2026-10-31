<?php

declare(strict_types=1);

namespace App\Bootstrap;

class ProjectSetup
{
    public const array SECRETS = ['JWT_KEY', 'BROADCAST_TOKEN_KEY', 'BROADCAST_API_KEY', 'WEBHOOK_USER_SECRET', 'WEBHOOK_NOTIFICATION_SECRET'];
    public const array DIRECTORIES = ['var/cache', 'var/log', 'var/twig', 'var/clockwork', 'var/storage/public', 'var/storage/private'];

    public function __construct(private string $root)
    {
    }

    public function run(): array
    {
        $path = $this->root . '/.env';
        $created = !file_exists($path);
        $source = $created ? $this->root . '/.env.example' : $path;
        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new \RuntimeException('Cannot read ' . basename($source));
        }
        $generated = [];
        foreach (self::SECRETS as $name) {
            $pattern = '/^(?:export[ \t]+)?' . $name . '[ \t]*=[ \t]*([^\r\n]*)/m';
            if (preg_match($pattern, $contents, $match) === 1) {
                $value = trim(preg_replace('/[ \t]+#.*$/', '', $match[1]));
                if (!in_array($value, ['', '""', "''"], true)) {
                    continue;
                }
                $contents = preg_replace($pattern, $name . '=' . bin2hex(random_bytes(32)), $contents, 1);
            } else {
                $contents = rtrim($contents, "\r\n") . "\n" . $name . '=' . bin2hex(random_bytes(32)) . "\n";
            }
            $generated[] = $name;
        }
        if ($created || $generated !== []) {
            $temporary = tempnam($this->root, '.setup-');
            if ($temporary === false) {
                throw new \RuntimeException('Cannot create temporary environment file');
            }
            try {
                $mode = $created ? 0600 : (fileperms($path) & 0777);
                if (!chmod($temporary, $mode) || file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $path)) {
                    throw new \RuntimeException('Cannot write .env');
                }
            } finally {
                if (file_exists($temporary)) {
                    unlink($temporary);
                }
            }
        }
        foreach (self::DIRECTORIES as $directory) {
            $target = $this->root . '/' . $directory;
            if (!is_dir($target) && !mkdir($target, 0770, true) && !is_dir($target)) {
                throw new \RuntimeException('Cannot create ' . $directory);
            }
            if (!is_writable($target)) {
                throw new \RuntimeException('Directory is not writable: ' . $directory);
            }
        }

        $this->storagePermissions();

        return ['environment_created' => $created, 'generated_keys' => $generated];
    }
    private function storagePermissions(): void
    {
        $public = $this->root . '/var/storage/public';
        $paths = [
            $this->root . '/var' => 0711,
            $this->root . '/var/storage' => 0711,
            $public => 0755,
            $this->root . '/var/storage/private' => 0700,
        ];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($public, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            if (!$file->isLink()) {
                $paths[$file->getPathname()] = $file->isDir() ? 0755 : 0644;
            }
        }
        foreach ($paths as $path => $mode) {
            if (!chmod($path, $mode)) {
                throw new \RuntimeException('Cannot set storage permissions: ' . $path);
            }
        }
    }

}
