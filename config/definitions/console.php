<?php

declare(strict_types=1);

use App\Queue\Commands\RetryFailedCommand;
use App\Queue\ServiceLocator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

return [
    ConsumeMessagesCommand::class => static function (ContainerInterface $container, LoggerInterface $logger): ConsumeMessagesCommand {
        return new ConsumeMessagesCommand(
            $container->get(RoutableMessageBus::class),
            new ServiceLocator(['async' => static fn (): DoctrineTransport => $container->get(DoctrineTransport::class)]),
            $container->get('queue.events'),
            $logger,
            ['async'],
        )->setAliases(['queue:work']);
    },

    StatsCommand::class => static fn (ContainerInterface $container): StatsCommand => new StatsCommand(
        $container->get('queue.transports'),
        ['async', 'failed'],
    )->setAliases(['queue:stats']),

    FailedMessagesShowCommand::class => static fn (ContainerInterface $container, PhpSerializer $serializer): FailedMessagesShowCommand => new FailedMessagesShowCommand(
        'failed',
        $container->get('queue.failed_transports'),
        $serializer,
    )->setAliases(['queue:failed']),

    FailedMessagesRemoveCommand::class => static fn (ContainerInterface $container, PhpSerializer $serializer): FailedMessagesRemoveCommand => new FailedMessagesRemoveCommand(
        'failed',
        $container->get('queue.failed_transports'),
        $serializer,
    )->setAliases(['queue:forget']),

    RetryFailedCommand::class => static fn (ContainerInterface $container, EntityManagerInterface $em): RetryFailedCommand => new RetryFailedCommand(
        $container->get(DoctrineTransport::class),
        $container->get('queue.failed'),
        $em,
    ),
];
