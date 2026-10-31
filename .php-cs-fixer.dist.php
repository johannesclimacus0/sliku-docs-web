<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__.'/app',
        __DIR__.'/src',
        __DIR__.'/config',
        __DIR__.'/routes',
        __DIR__.'/database',
        __DIR__.'/public',
        __DIR__.'/tools',
        __DIR__.'/tests',
    ])
    ->append([
        __FILE__,
        __DIR__.'/bin/console',
        __DIR__.'/bin/doctrine',
        __DIR__.'/bin/fresh',
        __DIR__.'/bin/prune-sessions',
        __DIR__.'/bin/test-database',
        __DIR__.'/bin/ui',
        __DIR__.'/bin/setup',
    ]);

return new Config()
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'new_expression_parentheses' => ['use_parentheses' => false],
        'final_class' => false,
    ])
    ->setCacheFile(__DIR__.'/var/php-cs-fixer.cache')
    ->setFinder($finder);
