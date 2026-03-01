<?php declare(strict_types=1);

use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Logging\Factories\SymfonyLoggingFactory;
use Cognesy\Logging\Integrations\Symfony\DependencyInjection\InstructorLoggingExtension;
use Cognesy\Logging\Integrations\Symfony\InstructorLoggingBundle;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class HandlesEventsTraitConsumer
{
    use HandlesEvents;
}

function compiledLoggingContainer(string $preset): ContainerBuilder
{
    $container = new ContainerBuilder();
    $container->register('logger', NullLogger::class)->setPublic(true);
    $container->register(HandlesEventsTraitConsumer::class, HandlesEventsTraitConsumer::class)->setPublic(true);

    $bundle = new InstructorLoggingBundle();
    $bundle->build($container);

    $extension = new InstructorLoggingExtension();
    $extension->load([['enabled' => true, 'preset' => $preset]], $container);

    $container->compile();

    return $container;
}

it('wires trait-based handlers and preserves preset factory method', function (string $preset, string $expectedFactoryMethod) {
    $container = compiledLoggingContainer($preset);

    $consumerDefinition = $container->findDefinition(HandlesEventsTraitConsumer::class);
    $wiretapCalls = array_values(array_filter(
        $consumerDefinition->getMethodCalls(),
        fn(array $call): bool => $call[0] === 'wiretap'
    ));

    expect($wiretapCalls)->toHaveCount(1);

    $wiretapArgument = $wiretapCalls[0][1][0];
    $isReferenceOrDefinition = $wiretapArgument instanceof Reference || $wiretapArgument instanceof Definition;
    expect($isReferenceOrDefinition)->toBeTrue();

    if ($wiretapArgument instanceof Reference) {
        expect((string) $wiretapArgument)->toBe('instructor_logging.pipeline_factory');
    }

    if ($wiretapArgument instanceof Definition) {
        expect($wiretapArgument->getFactory())->toBe([SymfonyLoggingFactory::class, $expectedFactoryMethod]);
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
