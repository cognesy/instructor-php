<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Unit\CompositionPrimitiveDecision;

use ArrayIterator;
use ArrayObject;
use Closure;
use Cognesy\Utils\Context\Context;
use Cognesy\Utils\Context\Layer;
use Countable;
use Iterator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

interface SpikeClock {}

final class FirstSpikeClock implements SpikeClock {}

final class SecondSpikeClock implements SpikeClock {}

final readonly class SpikeRunner
{
    public function __construct(public SpikeClock $clock) {}
}

final readonly class SpikeDefinition
{
    /**
     * @param  class-string  $provides
     * @param  list<class-string>  $requires
     * @param  Closure(array<class-string, object>): object  $factory
     */
    public function __construct(
        public string $id,
        public string $provides,
        public array $requires,
        public Closure $factory,
    ) {}
}

/** Executable named-factory candidate, intentionally scoped to this decision. */
final class NamedFactoryCandidate
{
    /** @param list<SpikeDefinition> $definitions */
    public function __construct(private array $definitions) {}

    /** @return array<class-string, object> */
    public function boot(): array {
        self::preflight($this->definitions);
        $services = [];
        $pending = $this->definitions;
        while ($pending !== []) {
            foreach ($pending as $index => $definition) {
                if (array_diff($definition->requires, array_keys($services)) !== []) {
                    continue;
                }
                $service = ($definition->factory)($services);
                if (!$service instanceof $definition->provides) {
                    throw new RuntimeException("{$definition->id} returned an invalid provider.");
                }
                $services[$definition->provides] = $service;
                unset($pending[$index]);

                continue 2;
            }
            throw new RuntimeException('Construction order could not be resolved.');
        }

        return $services;
    }

    /** @param list<SpikeDefinition> $definitions */
    public static function preflight(array $definitions): void {
        $errors = [];
        $ids = [];
        $providers = [];
        foreach ($definitions as $definition) {
            if (isset($ids[$definition->id])) {
                $errors[] = "duplicate module {$definition->id}";
            }
            if (isset($providers[$definition->provides])) {
                $errors[] = "duplicate provider {$definition->provides}";
            }
            $ids[$definition->id] = true;
            $providers[$definition->provides] = true;
        }
        foreach ($definitions as $definition) {
            foreach ($definition->requires as $requirement) {
                if (!isset($providers[$requirement])) {
                    $errors[] = "missing {$requirement} required by {$definition->id}";
                }
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }
    }
}

/** Constrains Context/Layer with the extra metadata its raw API lacks. */
final class ContextLayerCandidate
{
    /** @param list<SpikeDefinition> $definitions */
    public function __construct(private array $definitions) {}

    public function boot(): Context {
        NamedFactoryCandidate::preflight($this->definitions);
        $available = [];
        $pending = $this->definitions;
        $layer = null;
        while ($pending !== []) {
            foreach ($pending as $index => $definition) {
                if (array_diff($definition->requires, $available) !== []) {
                    continue;
                }
                $next = Layer::providesFrom(
                    $definition->provides,
                    static function (Context $context) use ($definition): object {
                        $dependencies = [];
                        foreach ($definition->requires as $requirement) {
                            $dependencies[$requirement] = $context->get($requirement);
                        }

                        return ($definition->factory)($dependencies);
                    },
                );
                $layer = $layer === null ? $next : $next->dependsOn($layer);
                $available[] = $definition->provides;
                unset($pending[$index]);

                continue 2;
            }
            throw new RuntimeException('Construction order could not be resolved.');
        }

        return $layer?->applyTo(Context::empty()) ?? Context::empty();
    }
}

/** @return list<SpikeDefinition> */
function spikeDefinitions(): array {
    return [
        new SpikeDefinition(
            id: 'clock.first',
            provides: SpikeClock::class,
            requires: [],
            factory: static fn (array $dependencies): SpikeClock => new FirstSpikeClock(),
        ),
        new SpikeDefinition(
            id: 'runner',
            provides: SpikeRunner::class,
            requires: [SpikeClock::class],
            factory: static fn (array $dependencies): SpikeRunner => new SpikeRunner($dependencies[SpikeClock::class]),
        ),
    ];
}

it('constructs the same typed two-module graph with both candidates', function (): void {
    $named = (new NamedFactoryCandidate(spikeDefinitions()))->boot();
    $context = (new ContextLayerCandidate(spikeDefinitions()))->boot();

    expect($named[SpikeRunner::class])->toBeInstanceOf(SpikeRunner::class)
        ->and($named[SpikeRunner::class]->clock)->toBeInstanceOf(FirstSpikeClock::class)
        ->and($context->get(SpikeRunner::class)->clock)->toBeInstanceOf(FirstSpikeClock::class);
});

it('exposes why raw Context Layer merge cannot be Tell admission', function (): void {
    $context = Layer::provides(SpikeClock::class, new FirstSpikeClock())
        ->merge(Layer::provides(SpikeClock::class, new SecondSpikeClock()))
        ->applyTo(Context::empty());

    expect($context->get(SpikeClock::class))->toBeInstanceOf(SecondSpikeClock::class);
});

it('rejects duplicate singletons and aggregates missing requirements for both candidates', function (string $candidate): void {
    $definitions = [
        ...spikeDefinitions(),
        new SpikeDefinition('clock.second', SpikeClock::class, [], static fn (array $dependencies): SpikeClock => new SecondSpikeClock()),
        new SpikeDefinition('missing.one', Countable::class, [LoggerInterface::class], static fn (array $dependencies): Countable => new ArrayObject()),
        new SpikeDefinition('missing.two', Iterator::class, [EventDispatcherInterface::class], static fn (array $dependencies): Iterator => new ArrayIterator()),
    ];
    $boot = match ($candidate) {
        'named factories' => static fn (): array => (new NamedFactoryCandidate($definitions))->boot(),
        'context layer adapter' => static fn (): Context => (new ContextLayerCandidate($definitions))->boot(),
    };

    try {
        $boot();
        throw new RuntimeException('Expected candidate preflight to fail.');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toContain('duplicate provider')
            ->toContain(LoggerInterface::class)
            ->toContain(EventDispatcherInterface::class);
    }
})->with(['named factories', 'context layer adapter']);

it('keeps Context and Layer out of Tell product code', function (): void {
    $sourceRoot = dirname(__DIR__, 2) . '/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));
    $source = '';
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $contents = file_get_contents($file->getPathname());
            $source .= is_string($contents) ? $contents : '';
        }
    }

    expect($source)->not->toContain('Cognesy\\Utils\\Context');
});
