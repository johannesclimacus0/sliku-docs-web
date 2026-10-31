<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

return [
    AccessDecisionManagerInterface::class => static fn (ContainerInterface $container): AccessDecisionManagerInterface => new AccessDecisionManager(
        array_map(static fn (string $class) => $container->get($class), $container->get('registrations.voters')),
    ),
];
