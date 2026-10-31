<?php

declare(strict_types=1);

use App\Config\Config;
use App\Debug\Profiler;
use App\Debug\SqlMiddleware;

use function DI\get;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Psr\Cache\CacheItemPoolInterface;

return [
    EntityManager::class => static function (Config $config, Profiler $profiler, CacheItemPoolInterface $cache): EntityManager {
        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../../src/Entity'],
            isDevMode: $config->get('app.debug', false),
            cache: $cache,
        );

        $ormConfig->enableNativeLazyObjects(true);

        $dbalConfig = new Configuration();
        if ($profiler->clockwork() !== null) {
            $dbalConfig->setMiddlewares([new SqlMiddleware($profiler)]);
        }

        $connection = DriverManager::getConnection([
            'driver' => $config->get('database.driver'),
            'host' => $config->get('database.host'),
            'port' => $config->get('database.port'),
            'dbname' => $config->get('database.name'),
            'user' => $config->get('database.username'),
            'password' => $config->get('database.password'),
            'driverOptions' => [PDO::ATTR_TIMEOUT => $config->get('database.connect_timeout', 3)],
        ], $dbalConfig);

        $unmanaged = $config->get('database.unmanaged_tables');
        $connection->getConfiguration()->setSchemaAssetsFilter(
            static fn (string $asset): bool => !in_array($asset, $unmanaged, true),
        );

        return new EntityManager($connection, $ormConfig);
    },

    EntityManagerInterface::class => get(EntityManager::class),
];
