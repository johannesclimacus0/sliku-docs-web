<?php

declare(strict_types=1);

use App\Config\Config;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\Mime\BodyRendererInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

return [
    Environment::class => static function (Config $config): Environment {
        $root = dirname(__DIR__, 2);

        return new Environment(new FilesystemLoader($root . '/templates'), [
            'cache' => $config->get('app.debug') ? false : $root . '/var/twig',
            'auto_reload' => (bool) $config->get('app.debug'),
            'strict_variables' => true,
            'autoescape' => 'name',
        ]);
    },

    BodyRendererInterface::class => static fn (Environment $twig, Config $config): BodyRendererInterface => new BodyRenderer(
        $twig,
        ['appName' => $config->get('app.app_name', 'Workspace')],
    ),
];
