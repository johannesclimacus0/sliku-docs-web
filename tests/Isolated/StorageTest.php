<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Storage\Storage;
use League\Flysystem\PathTraversalDetected;
use League\Flysystem\UnableToReadFile;
use Tests\Support\IsolatedTestCase;
use Tests\Support\TestEnvironment;

class StorageTest extends IsolatedTestCase
{
    public function testFileCanBeWrittenReplacedReadAndDeleted(): void
    {
        $storage = $this->env->get(Storage::class);
        $storage->put('avatars/example.txt', 'first');
        self::assertTrue($storage->exists('avatars/example.txt'));
        self::assertSame('first', $storage->get('avatars/example.txt'));
        $storage->put('avatars/example.txt', 'replacement');
        self::assertSame('replacement', $storage->get('avatars/example.txt'));
        $storage->delete('avatars/example.txt');
        self::assertFalse($storage->exists('avatars/example.txt'));
    }

    public function testStreamsCanBeWrittenAndRead(): void
    {
        $storage = $this->env->get(Storage::class);
        $input = fopen('php://temp', 'w+');
        fwrite($input, "binary\0contents");
        rewind($input);
        try {
            $storage->putStream('stream.bin', $input);
        } finally {
            fclose($input);
        }
        $output = $storage->readStream('stream.bin');
        try {
            self::assertSame("binary\0contents", stream_get_contents($output));
        } finally {
            fclose($output);
        }
    }

    public function testPathCannotEscapeTemporaryDisk(): void
    {
        $this->expectException(PathTraversalDetected::class);
        $this->env->get(Storage::class)->put('../outside.txt', 'forbidden');
    }

    public function testReadingMissingFileThrows(): void
    {
        $this->expectException(UnableToReadFile::class);
        $this->env->get(Storage::class)->get('missing.txt');
    }

    public function testPublicUrlEncodesPathSegments(): void
    {
        self::assertSame('/media/avatars/my%20photo.png', $this->env->get(Storage::class)->url('avatars/my photo.png'));
    }
    public function testNamedDisksAreIndependentAndCached(): void
    {
        $storage = $this->env->get(Storage::class);
        $storage->disk('public')->put('same.txt', 'public');
        $storage->disk('private')->put('same.txt', 'private');
        self::assertSame('public', $storage->get('same.txt'));
        self::assertSame('private', $storage->disk('private')->get('same.txt'));
        self::assertSame($storage->disk('private'), $storage->disk('private'));
        $storage->delete('same.txt');
        self::assertTrue($storage->disk('private')->exists('same.txt'));
        self::assertFileExists($this->env->directory . '/private/same.txt');
    }

    public function testPrivateDiskHasNoPublicUrl(): void
    {
        $this->expectException(\LogicException::class);
        $this->env->get(Storage::class)->disk('private')->url('secret.txt');
    }

    public function testUnknownDiskIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->env->get(Storage::class)->disk('missing');
    }

    public function testPrivateDiskRejectsTraversal(): void
    {
        $this->expectException(PathTraversalDetected::class);
        $this->env->get(Storage::class)->disk('private')->put('../public/leak.txt', 'secret');
    }

    public function testDefaultCanBePrivateAndPublicRemainsExplicit(): void
    {
        $environment = new TestEnvironment(['storage' => ['default' => 'private']]);
        try {
            $storage = $environment->get(Storage::class);
            $storage->put('report.txt', 'private');
            self::assertTrue($storage->disk('private')->exists('report.txt'));
            self::assertFalse($storage->disk('public')->exists('report.txt'));
            self::assertSame('/media/avatars/a.png', $storage->disk('public')->url('avatars/a.png'));
        } finally {
            $environment->close();
        }
    }

    public function testProductionRootsAreNeverUsedByTheTestEnvironment(): void
    {
        $environment = new TestEnvironment(['storage' => ['disks' => [
            'public' => ['root' => '/production/public'],
            'private' => ['root' => '/production/private'],
        ]]]);
        try {
            foreach (['public', 'private'] as $name) {
                $environment->get(Storage::class)->disk($name)->put('probe.txt', 'test');
                self::assertFileExists($environment->directory . '/' . $name . '/probe.txt');
            }
        } finally {
            $environment->close();
        }
    }

    public function testAdditionalConfiguredDiskWorks(): void
    {
        $environment = new TestEnvironment(['storage' => ['disks' => [
            'exports' => ['driver' => 'local', 'root' => '/unused/by/tests'],
        ]]]);
        try {
            $storage = $environment->get(Storage::class);
            $storage->disk('exports')->put('report.csv', 'id,email');
            self::assertSame('id,email', $storage->disk('exports')->get('report.csv'));
            self::assertFalse($storage->disk('public')->exists('report.csv'));
        } finally {
            $environment->close();
        }
    }

    public function testUnsupportedDriverIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new \App\Storage\DiskFactory()->create(['driver' => 'missing', 'root' => '/unused']);
    }

    public function testDiskVisibilityControlsFileAndDirectoryPermissions(): void
    {
        foreach (['public' => [0755, 0644], 'private' => [0700, 0600]] as $visibility => [$directoryMode, $fileMode]) {
            $root = $this->env->directory . '/permissions-' . $visibility;
            $disk = new \App\Storage\DiskFactory()->create(['driver' => 'local', 'root' => $root, 'visibility' => $visibility]);
            $disk->write('nested/file.txt', 'hello');
            clearstatcache();
            self::assertSame($directoryMode, fileperms($root . '/nested') & 0777);
            self::assertSame($fileMode, fileperms($root . '/nested/file.txt') & 0777);
        }
    }

}
