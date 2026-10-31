<?php

declare(strict_types=1);

use App\Config\Config;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return [
    HttpClientInterface::class => static fn (Config $config): HttpClientInterface => HttpClient::create(
        $config->get('http_client'),
    ),
];
