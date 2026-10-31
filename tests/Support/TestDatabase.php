<?php

declare(strict_types=1);

namespace Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Dotenv\Dotenv;
use RuntimeException;

class TestDatabase
{
    public const NAME = 'slim_test';

    public static function settings(): array
    {
        $_ENV += getenv();
        Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
        $settings = [];
        foreach (glob(dirname(__DIR__, 2) . '/config/settings/*.php') ?: [] as $file) {
            $settings[basename($file, '.php')] = require $file;
        }
        $settings['webhooks'] = ['endpoints' => []];
        return array_replace_recursive($settings, [
            'broadcast' => ['enabled' => false],
            'app' => ['env' => 'testing', 'debug' => false, 'jwt_key' => str_repeat('integration-test-key-', 4)],
            'database' => ['name' => self::NAME],
            'email_verification' => ['required' => true, 'frontend_url' => 'https://frontend.example.test'],
        ]);
    }

    public static function params(array $database): array
    {
        return [
            'driver' => 'pdo_pgsql', 'host' => $database['host'], 'port' => $database['port'],
            'dbname' => self::NAME, 'user' => $database['username'], 'password' => $database['password'],
            'driverOptions' => [\PDO::ATTR_TIMEOUT => 3],
        ];
    }

    public static function assertSafe(Connection $connection): void
    {
        if ($connection->getParams()['driver'] !== 'pdo_pgsql'
            || $connection->fetchOne('SELECT current_database()') !== self::NAME) {
            throw new RuntimeException('Tests may only clean the PostgreSQL database ' . self::NAME);
        }
    }

    public static function create(): void
    {
        $settings = self::settings();
        $params = self::params($settings['database']);
        $params['dbname'] = 'postgres';
        $admin = DriverManager::getConnection($params);
        try {
            if (!$admin->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [self::NAME])) {
                $admin->executeStatement('CREATE DATABASE ' . $admin->getDatabasePlatform()->quoteIdentifier(self::NAME));
            }
        } finally {
            $admin->close();
        }
        $connection = DriverManager::getConnection(self::params($settings['database']));
        try {
            self::assertSafe($connection);
            self::migrate($connection);
        } finally {
            $connection->close();
        }
    }

    public static function migrate(Connection $connection): void
    {
        self::assertSafe($connection);
        $factory = DependencyFactory::fromConnection(
            new PhpFile(dirname(__DIR__, 2) . '/database/migration.php'),
            new ExistingConnection($connection),
        );
        $factory->getMetadataStorage()->ensureInitialized();
        $version = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
        $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($version);
        $configuration = new MigratorConfiguration()->setAllOrNothing(true);
        $factory->getMigrator()->migrate($plan, $configuration);
    }
}
