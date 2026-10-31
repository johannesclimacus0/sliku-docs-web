<?php

declare(strict_types=1);

use App\Config\Config;
use App\Queue\ServiceLocator;
use App\Queue\WorkerStateSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as QueueConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\FailedMessageProcessingMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

return [
    DoctrineTransport::class => static function (
        Config $config,
        EntityManagerInterface $em,
        PhpSerializer $serializer,
    ): DoctrineTransport {
        $connection = new QueueConnection(
            $config->get('queue.transport'),
            $em->getConnection(),
        );

        return new DoctrineTransport($connection, $serializer);
    },

    'queue.failed' => static function (Config $config, EntityManagerInterface $em, PhpSerializer $serializer): DoctrineTransport {
        $options = $config->get('queue.transport');
        $options['queue_name'] = $config->get('queue.failed_queue');

        return new DoctrineTransport(new QueueConnection($options, $em->getConnection()), $serializer);
    },

    'queue.transports' => static fn (ContainerInterface $container): ServiceLocator => new ServiceLocator([
        'async' => static fn (): DoctrineTransport => $container->get(DoctrineTransport::class),
        'failed' => static fn (): DoctrineTransport => $container->get('queue.failed'),
    ]),

    'queue.failed_transports' => static fn (ContainerInterface $container): ServiceLocator => new ServiceLocator([
        'failed' => static fn (): DoctrineTransport => $container->get('queue.failed'),
    ]),

    MessageBusInterface::class => static function (
        Config $config,
        ContainerInterface $container,
        LoggerInterface $logger,
    ): MessageBusInterface {
        $handlers = [];

        foreach ($container->get('registrations.jobs') as $job => $handler) {
            $handlers[$job] = [
                new HandlerDescriptor(
                    static fn (object $message) => $container->get($handler)($message),
                    ['alias' => $handler],
                ),
            ];
        }

        $send = new SendMessageMiddleware(new SendersLocator(['*' => ['async']], $container->get('queue.transports')));
        $send->setLogger($logger);
        $handle = new HandleMessageMiddleware(new HandlersLocator($handlers));
        $handle->setLogger($logger);

        return new MessageBus([
            new AddBusNameStampMiddleware('queue.bus'),
            new FailedMessageProcessingMiddleware(),
            $send,
            $handle,
        ]);
    },

    RoutableMessageBus::class => static fn (MessageBusInterface $bus): RoutableMessageBus => new RoutableMessageBus(
        new ServiceLocator(['queue.bus' => static fn (): MessageBusInterface => $bus]),
        $bus,
    ),

    'queue.events' => static function (
        Config $config,
        ContainerInterface $container,
        LoggerInterface $logger,
        WorkerStateSubscriber $state,
    ): EventDispatcher {
        $events = new EventDispatcher();
        $retry = $config->get('queue.retry');
        $strategies = new ServiceLocator([
            'async' => static fn (): MultiplierRetryStrategy => new MultiplierRetryStrategy(
                $retry['max_retries'],
                $retry['delay_ms'],
                $retry['multiplier'],
                $retry['max_delay_ms'],
            ),
        ]);

        $events->addSubscriber($state);
        $events->addSubscriber(new AddErrorDetailsStampListener());
        $events->addSubscriber(new SendFailedMessageForRetryListener(
            $container->get('queue.transports'),
            $strategies,
            $logger,
            $events,
        ));
        $events->addSubscriber(new SendFailedMessageToFailureTransportListener(
            new ServiceLocator(['async' => static fn (): DoctrineTransport => $container->get('queue.failed')]),
            $logger,
            ['async' => 'failed'],
        ));

        return $events;
    },
];
