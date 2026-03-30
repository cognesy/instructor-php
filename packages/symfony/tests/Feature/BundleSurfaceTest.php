<?php

declare(strict_types=1);

use Cognesy\Instructor\Symfony\DependencyInjection\Configuration;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Cognesy\Instructor\Symfony\InstructorSymfonyBundle;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Symfony\Component\Config\Definition\Processor;

it('defines the symfony bundle surface', function (): void {
    $bundle = new InstructorSymfonyBundle;

    expect($bundle->getContainerExtension())
        ->toBeInstanceOf(InstructorSymfonyExtension::class)
        ->and($bundle->getContainerExtension()?->getAlias())
        ->toBe('instructor');
});

it('boots the symfony bundle through a reusable test kernel harness', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            expect($app->container()->has('kernel'))->toBeTrue()
                ->and($app->kernel()->getBundles())->toHaveKey('InstructorSymfonyBundle');
        },
        instructorConfig: [
        'connections' => [
            'default' => 'openai',
            'items' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
    ],
    );
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
