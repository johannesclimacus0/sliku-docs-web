<?php

declare(strict_types=1);
use App\Console\ClassGenerator;
use App\Console\MakeClassCommand;

$definitions = [ClassGenerator::class => static fn (): ClassGenerator => new ClassGenerator(dirname(__DIR__, 2))];
foreach (array_keys(ClassGenerator::TYPES) as $type) {
    $definitions['make.' . $type] = static fn (ClassGenerator $generator): MakeClassCommand => new MakeClassCommand($type, $generator);
}
return $definitions;
