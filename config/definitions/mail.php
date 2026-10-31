<?php

declare(strict_types=1);

use App\Config\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

return [
    TransportInterface::class => static function (Config $config, LoggerInterface $logger): TransportInterface {
        $dsn = $config->get('mail.dsn');
        if (!is_string($dsn) || $dsn === '') {
            throw new LogicException('MAILER_DSN must be configured');
        }

        return Transport::fromDsn($dsn, logger: $logger);
    },

    MailerInterface::class => static fn (TransportInterface $transport): MailerInterface => new Mailer($transport),
];
