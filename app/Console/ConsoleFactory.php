<?php

declare(strict_types=1);

namespace App\Console;

use DI\Container;
use Dotenv\Dotenv;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

class ConsoleFactory
{
    public static function create(?Container $container = null): Application
    {
        $root = dirname(__DIR__, 2);
        Dotenv::createImmutable($root)->safeLoad();
        $container ??= (require $root . '/config/container.php')();

        $application = new Application('Slim Console');
        $application->setAutoExit(false);
        $commands = $container->get('registrations.commands');
        foreach (array_keys(ClassGenerator::TYPES) as $type) {
            $commands['make:' . $type] = 'make.' . $type;
        }
        $application->setCommandLoader(new ContainerCommandLoader($container, $commands));

        return $application;
    }
}
