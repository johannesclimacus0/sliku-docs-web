<?php

declare(strict_types=1);
use App\Cache\Cache;
use App\Scheduler\Scheduler;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

return [
    Scheduler::class => static fn (ContainerInterface $container, Cache $cache, Redis $redis, LoggerInterface $logger): Scheduler
    => new Scheduler($container->get('registrations.schedule'), $cache, new LockFactory(new RedisStore($redis)), $logger)];
