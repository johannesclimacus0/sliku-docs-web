<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Contracts\Service\ServiceLocatorTrait;
use Symfony\Contracts\Service\ServiceProviderInterface;

class ServiceLocator implements ServiceProviderInterface
{
    use ServiceLocatorTrait;
}
