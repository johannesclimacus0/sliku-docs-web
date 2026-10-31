<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class RecordingMailTransport extends AbstractTransport
{
    /** @var list<SentMessage> */
    public array $messages = [];

    protected function doSend(SentMessage $message): void
    {
        $this->messages[] = $message;
    }

    public function __toString(): string
    {
        return 'memory://';
    }
}
