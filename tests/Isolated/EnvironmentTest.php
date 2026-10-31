<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Cache\Cache;
use App\Storage\Storage;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestEnvironment;

class EnvironmentTest extends TestCase
{
    public function testEnvironmentsHaveIndependentCacheAndFiles(): void
    {
        $first = new TestEnvironment();
        $second = new TestEnvironment();

        try {
            $first->get(Cache::class)->set('example', 'value');
            $first->get(Storage::class)->put('example.txt', 'contents');
            self::assertNull($second->get(Cache::class)->get('example'));
            self::assertFalse($second->get(Storage::class)->exists('example.txt'));
            self::assertNotSame($first->directory, $second->directory);
        } finally {
            $first->close();
            $second->close();
        }
    }

    public function testClosingEnvironmentRemovesNestedFiles(): void
    {
        $environment = new TestEnvironment();
        $directory = $environment->directory;
        $environment->get(Storage::class)->put('nested/file.txt', 'temporary');
        $environment->close();
        $environment->close();

        self::assertDirectoryDoesNotExist($directory);
    }
}
