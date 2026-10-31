<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

return [
    EventDispatcherInterface::class => static function (ContainerInterface $container): EventDispatcherInterface {
        $dispatcher = new EventDispatcher();
        $registrations = $container->get('registrations.events');
        foreach ($registrations['subscribers'] as $class) {
            $dispatcher->addSubscriber($container->get($class));
        }
        foreach ($registrations['listeners'] as $event => $listeners) {
            foreach ($listeners as $class) {
                $dispatcher->addListener($event, $container->get($class));
            }
        }
        return $dispatcher;
    },
];
