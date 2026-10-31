<?php

declare(strict_types=1);

namespace Tests\Isolated;

use App\Config\Config;
use DI\Container;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class DoctrineConfigurationTest extends TestCase
{
    public static function debugModes(): array
    {
        return ['debug disabled' => [false], 'debug enabled' => [true]];
    }

    #[DataProvider('debugModes')]
    public function testDoctrineUsesInjectedCacheWithoutConnectingToExternalServices(bool $debug): void
    {
        $pool = new ArrayAdapter();
        $container = new Container(require dirname(__DIR__, 2) . '/config/definitions/doctrine.php');
        $container->set(CacheItemPoolInterface::class, $pool);
        $container->set(Config::class, new Config([
            'app' => ['env' => 'testing', 'debug' => $debug],
            'database' => ['driver' => 'pdo_pgsql', 'host' => 'must-not-connect.example.test',
                'port' => 5432, 'name' => 'slim_test', 'username' => 'test', 'password' => '',
                'unmanaged_tables' => []],
        ]));

        $em = $container->get(EntityManagerInterface::class);
        $configuration = $em->getConfiguration();
        self::assertSame($pool, $configuration->getMetadataCache());
        self::assertSame($pool, $configuration->getQueryCache());
        self::assertSame($pool, $configuration->getResultCache());
        self::assertFalse($em->getConnection()->isConnected());
    }
}
