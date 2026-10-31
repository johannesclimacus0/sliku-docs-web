<?php

declare(strict_types=1);

namespace Tests\Isolated;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

class TestDatabaseGuardTest extends TestCase
{
    public function testSettingsReadInheritedEnvironmentWithoutDependingOnVariablesOrder(): void
    {
        $previousEnv = $_ENV;
        $previousHost = getenv('DB_HOST');
        try {
            unset($_ENV['DB_HOST']);
            putenv('DB_HOST=ci-database.example.test');
            $settings = TestDatabase::settings();
            self::assertSame('ci-database.example.test', $settings['database']['host']);
            self::assertSame('slim_test', $settings['database']['name']);
        } finally {
            $_ENV = $previousEnv;
            putenv($previousHost === false ? 'DB_HOST' : 'DB_HOST=' . $previousHost);
        }
    }

    public function testMigrationRefusesToRunOnAnotherDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn(['driver' => 'pdo_pgsql']);
        $connection->method('fetchOne')->willReturn('slim');
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(RuntimeException::class);
        TestDatabase::migrate($connection);
    }

    public function testGuardRejectsNonPostgresConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn(['driver' => 'pdo_sqlite']);
        $connection->expects(self::never())->method('fetchOne');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('slim_test');
        TestDatabase::assertSafe($connection);
    }

    public function testGuardRejectsActualDatabaseNameEvenWhenConfiguredNameLooksSafe(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn(['driver' => 'pdo_pgsql', 'dbname' => 'slim_test']);
        $connection->expects(self::once())->method('fetchOne')->with('SELECT current_database()')->willReturn('slim');
        $connection->expects(self::never())->method('executeStatement');
        $this->expectException(RuntimeException::class);
        TestDatabase::assertSafe($connection);
    }
}
