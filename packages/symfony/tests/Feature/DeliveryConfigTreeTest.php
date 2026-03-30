<?php

declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates;
use Cognesy\Instructor\Symfony\DependencyInjection\Configuration;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Cognesy\Instructor\Symfony\InstructorSymfonyBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

it('defines a typed delivery config tree with explicit progress, cli, and messenger settings', function (): void {
    $processor = new Processor;
    $config = $processor->processConfiguration(new Configuration, [[
        'delivery' => [
            'progress' => [
                'enabled' => false,
            ],
            'cli' => [
                'enabled' => true,
                'use_colors' => false,
                'show_timestamps' => false,
            ],
            'messenger' => [
                'enabled' => true,
                'bus_service' => 'async.bus',
                'observe_events' => ['App\\Event\\RuntimeObserved'],
            ],
        ],
    ]]);

    expect($config['delivery'])->toMatchArray([
        'messenger' => [
            'enabled' => true,
            'bus_service' => 'async.bus',
            'observe_events' => ['App\\Event\\RuntimeObserved'],
        ],
        'progress' => [
            'enabled' => false,
        ],
        'cli' => [
            'enabled' => true,
            'use_colors' => false,
            'show_timestamps' => false,
        ],
    ]);
});

it('rejects empty messenger bus services in the delivery config tree', function (): void {
    $processor = new Processor;

    expect(static fn () => $processor->processConfiguration(new Configuration, [[
        'delivery' => [
            'messenger' => [
                'enabled' => true,
                'bus_service' => '',
            ],
        ],
    ]]))->toThrow(InvalidConfigurationException::class);
});

it('keeps delivery compiler-pass wiring explicit across disabled progress and enabled cli settings', function (): void {
    $container = compiledSymfonyDeliveryContainer([
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        'delivery' => [
            'progress' => [
                'enabled' => false,
            ],
            'cli' => [
                'enabled' => true,
                'use_colors' => false,
                'show_timestamps' => false,
            ],
        ],
    ]);

    $eventBusDefinition = $container->findDefinition(CanHandleEvents::class);
    $progressBusDefinition = $container->findDefinition(CanHandleProgressUpdates::class);

    expect(referencesWiretap($eventBusDefinition, 'instructor.delivery.progress_bridge'))->toBeFalse()
        ->and(referencesWiretap($progressBusDefinition, 'Cognesy\\Instructor\\Symfony\\Delivery\\Cli\\SymfonyCliObservationPrinter'))->toBeTrue()
        ->and($container->getParameter('instructor.delivery.progress.enabled'))->toBeFalse()
        ->and($container->getParameter('instructor.delivery.cli.enabled'))->toBeTrue()
        ->and($container->getParameter('instructor.delivery.cli.use_colors'))->toBeFalse()
        ->and($container->getParameter('instructor.delivery.cli.show_timestamps'))->toBeFalse();
});

/**
 * @param array<string, mixed> $config
 */
function compiledSymfonyDeliveryContainer(array $config): ContainerBuilder
{
    $container = new ContainerBuilder;

    $bundle = new InstructorSymfonyBundle;
    $bundle->build($container);

    $extension = new InstructorSymfonyExtension;
    $extension->load([$config], $container);
    $container->compile();

    return $container;
}

function referencesWiretap(Definition $definition, string $serviceId): bool
{
    foreach ($definition->getMethodCalls() as [$method, $arguments]) {
        $listener = $arguments[0] ?? null;

        if ($method !== 'wiretap') {
            continue;
        }

        if ($listener instanceof Reference && (string) $listener === $serviceId) {
            return true;
        }
    }

    return false;
}
