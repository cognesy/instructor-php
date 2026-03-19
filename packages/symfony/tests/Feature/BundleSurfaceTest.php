<?php

declare(strict_types=1);

require_once __DIR__.'/../../src/DependencyInjection/InstructorSymfonyExtension.php';
require_once __DIR__.'/../../src/DependencyInjection/Configuration.php';
require_once __DIR__.'/../../src/InstructorSymfonyBundle.php';

use Cognesy\Instructor\Symfony\DependencyInjection\Configuration;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Cognesy\Instructor\Symfony\InstructorSymfonyBundle;
use Symfony\Component\Config\Definition\Processor;

it('defines the symfony bundle surface', function (): void {
    $bundle = new InstructorSymfonyBundle;

    expect($bundle->getContainerExtension())
        ->toBeInstanceOf(InstructorSymfonyExtension::class)
        ->and($bundle->getContainerExtension()?->getAlias())
        ->toBe('instructor');
});

it('reserves the instructor config tree for the package surface', function (): void {
    $processor = new Processor;
    $config = $processor->processConfiguration(new Configuration, []);

    expect($config)->toHaveKeys([
        'connections',
        'embeddings',
        'extraction',
        'http',
        'events',
        'agent_ctrl',
        'agents',
        'sessions',
        'telemetry',
        'logging',
        'testing',
        'delivery',
    ]);
});
