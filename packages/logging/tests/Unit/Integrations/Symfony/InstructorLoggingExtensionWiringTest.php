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

function compiledLoggingContainer(
    string $preset,
    ?string $eventBusService = null,
    bool $captureDeprecation = true,
): ContainerBuilder
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
    match ($captureDeprecation) {
        true => captureLoggingBundleDeprecations(static function () use ($extension, $extensionConfig, $container): void {
            $extension->load([$extensionConfig], $container);
        }),
        false => $extension->load([$extensionConfig], $container),
    };

    $container->compile();

    return $container;
}

/** @return list<string> */
function captureLoggingBundleDeprecations(callable $callback): array
{
    $messages = [];

    set_error_handler(static function (int $severity, string $message) use (&$messages): bool {
        if ($severity !== E_USER_DEPRECATED) {
            return false;
        }

        $messages[] = $message;

        return true;
    });

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $messages;
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
    ['development', 'defaultSetup'],
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
    captureLoggingBundleDeprecations(static function () use ($extension, $container): void {
        $extension->load([[
            'enabled' => true,
            'preset' => 'default',
            'event_bus_service' => 'app.custom_event_bus',
        ]], $container);
    });

    $container->compile();

    $eventBusDefinition = $container->findDefinition('app.custom_event_bus');
    $wiretapCalls = array_values(array_filter(
        $eventBusDefinition->getMethodCalls(),
        fn(array $call): bool => $call[0] === 'wiretap'
    ));

    expect($wiretapCalls)->toHaveCount(1);
});

it('emits a deprecation when the legacy Symfony logging bundle path is loaded', function () {
    $messages = captureLoggingBundleDeprecations(static function (): void {
        compiledLoggingContainer('development', captureDeprecation: false);
    });

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('InstructorLoggingBundle')
        ->and($messages[0])->toContain('instructor.logging');
});
