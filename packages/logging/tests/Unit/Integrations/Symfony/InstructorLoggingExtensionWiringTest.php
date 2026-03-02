<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Logging\Factories\SymfonyLoggingFactory;
use Cognesy\Logging\Integrations\Symfony\DependencyInjection\InstructorLoggingExtension;
use Cognesy\Logging\Integrations\Symfony\InstructorLoggingBundle;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ExplicitEventBusConsumer implements CanHandleEvents
{
    public function dispatch(object $event): object
    {
        return $event;
    }

    public function getListenersForEvent(object $event): iterable
    {
        return [];
    }

    public function addListener(string $name, callable $listener, int $priority = 0): void {}

    public function wiretap(callable $listener): void {}
}

function compiledLoggingContainer(string $preset, ?string $eventBusService = null): ContainerBuilder
{
    $container = new ContainerBuilder();
    $container->register('logger', NullLogger::class)->setPublic(true);
    $container->register(CanHandleEvents::class, ExplicitEventBusConsumer::class)->setPublic(true);

    $bundle = new InstructorLoggingBundle();
    $bundle->build($container);

    $extension = new InstructorLoggingExtension();
    $extensionConfig = ['enabled' => true, 'preset' => $preset];
    if ($eventBusService !== null) {
        $extensionConfig['event_bus_service'] = $eventBusService;
    }
    $extension->load([$extensionConfig], $container);

    $container->compile();

    return $container;
}

it('wires explicit event bus service and preserves preset factory method', function (string $preset, string $expectedFactoryMethod) {
    $container = compiledLoggingContainer($preset);

    $eventBusDefinition = $container->findDefinition(CanHandleEvents::class);
    $wiretapCalls = array_values(array_filter(
        $eventBusDefinition->getMethodCalls(),
        fn(array $call): bool => $call[0] === 'wiretap'
    ));

    expect($wiretapCalls)->toHaveCount(1);

    $wiretapArgument = $wiretapCalls[0][1][0];
    $isReferenceOrDefinition = $wiretapArgument instanceof Reference || $wiretapArgument instanceof Definition;
    expect($isReferenceOrDefinition)->toBeTrue();

    if ($wiretapArgument instanceof Reference) {
        expect((string) $wiretapArgument)->toBe('instructor_logging.pipeline_listener');
    }

    if ($container->hasDefinition('instructor_logging.pipeline_factory')) {
        $pipelineFactoryDefinition = $container->findDefinition('instructor_logging.pipeline_factory');
        expect($pipelineFactoryDefinition->getFactory())->toBe([SymfonyLoggingFactory::class, $expectedFactoryMethod]);
    }
})->with([
    ['default', 'defaultSetup'],
    ['production', 'productionSetup'],
    ['custom', 'create'],
]);

it('supports custom event bus service id configuration', function () {
    $container = new ContainerBuilder();
    $container->register('logger', NullLogger::class)->setPublic(true);
    $container->register('app.custom_event_bus', ExplicitEventBusConsumer::class)->setPublic(true);

    $bundle = new InstructorLoggingBundle();
    $bundle->build($container);

    $extension = new InstructorLoggingExtension();
    $extension->load([[
        'enabled' => true,
        'preset' => 'default',
        'event_bus_service' => 'app.custom_event_bus',
    ]], $container);

    $container->compile();

    $eventBusDefinition = $container->findDefinition('app.custom_event_bus');
    $wiretapCalls = array_values(array_filter(
        $eventBusDefinition->getMethodCalls(),
        fn(array $call): bool => $call[0] === 'wiretap'
    ));

    expect($wiretapCalls)->toHaveCount(1);
});
