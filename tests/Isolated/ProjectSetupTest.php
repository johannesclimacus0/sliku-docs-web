<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Bootstrap\ProjectSetup;
use Tests\Support\IsolatedTestCase;

class ProjectSetupTest extends IsolatedTestCase
{
    public function testSetupCreatesEnvironmentSecretsAndDirectoriesAndIsRepeatable(): void
    {
        $root = $this->env->directory;
        copy(dirname(__DIR__, 2) . '/.env.example', $root . '/.env.example');
        $setup = new ProjectSetup($root);
        $result = $setup->run();
        self::assertTrue($result['environment_created']);
        $env = file_get_contents($root . '/.env');
        foreach (ProjectSetup::SECRETS as $name) {
            self::assertMatchesRegularExpression('/^' . $name . '=[a-f0-9]{64}$/m', $env);
        }
        foreach (ProjectSetup::DIRECTORIES as $path) {
            self::assertDirectoryExists($root . '/' . $path);
        }
        self::assertSame([], $setup->run()['generated_keys']);
        self::assertSame($env, file_get_contents($root . '/.env'));
    }

    public function testExistingValuesAndCommentsArePreserved(): void
    {
        $root = $this->env->directory;
        file_put_contents($root . '/.env', "# Keep this\nJWT_KEY=existing-secret\nBROADCAST_TOKEN_KEY=\"\"\nDB_PASSWORD=my-password\nCUSTOM=value\n");
        $result = new ProjectSetup($root)->run();
        self::assertFalse($result['environment_created']);
        $env = file_get_contents($root . '/.env');
        self::assertStringContainsString("# Keep this\nJWT_KEY=existing-secret\n", $env);
        self::assertStringContainsString('DB_PASSWORD=my-password', $env);
        self::assertStringContainsString('CUSTOM=value', $env);
        self::assertNotContains('JWT_KEY', $result['generated_keys']);
        self::assertMatchesRegularExpression('/^BROADCAST_TOKEN_KEY=[a-f0-9]{64}$/m', $env);
    }
    public function testSetupMakesExistingPublicFilesReadableWithoutOpeningPrivateStorage(): void
    {
        $root = $this->env->directory;
        copy(dirname(__DIR__, 2) . '/.env.example', $root . '/.env.example');
        mkdir($root . '/var/storage/public/avatars', 0700, true);
        file_put_contents($root . '/var/storage/public/avatars/photo.png', 'image');
        chmod($root . '/var/storage/public/avatars/photo.png', 0600);
        new ProjectSetup($root)->run();
        clearstatcache();
        self::assertSame(0711, fileperms($root . '/var') & 0777);
        self::assertSame(0711, fileperms($root . '/var/storage') & 0777);
        self::assertSame(0755, fileperms($root . '/var/storage/public/avatars') & 0777);
        self::assertSame(0644, fileperms($root . '/var/storage/public/avatars/photo.png') & 0777);
        self::assertSame(0700, fileperms($root . '/var/storage/private') & 0777);
    }

}
