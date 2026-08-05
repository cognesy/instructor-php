<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverRegistry;
use Cognesy\Polyglot\Inference\Drivers\SpecifiedInferenceDriver;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('returns the same bundled registry instance on every call', function () {
    // The bundled table is a compile-time constant; rebuilding it per implicitly-constructed
    // runtime cost ~9.9us and 29 registry clones.
    expect(BundledInferenceDrivers::registry())->toBe(BundledInferenceDrivers::registry());
})->group('driver-registry');

it('does not let a derived registry affect the shared one', function () {
    // The whole safety argument for sharing one instance: the registry is immutable, so a
    // caller customising it gets a copy and cannot reach the memoized object.
    $derived = BundledInferenceDrivers::registry()
        ->withoutDriver('openai')
        ->withDriver('custom-x', fn($config, $httpClient, $events) => new FakeInferenceDriver());

    expect($derived->has('openai'))->toBeFalse()
        ->and($derived->has('custom-x'))->toBeTrue()
        ->and(BundledInferenceDrivers::registry()->has('openai'))->toBeTrue()
        ->and(BundledInferenceDrivers::registry()->has('custom-x'))->toBeFalse();
})->group('driver-registry');

it('builds fromArray without folding the public wither over the map', function () {
    // "fromArray() performs zero clones" cannot be asserted by counting: the class is final,
    // so __clone cannot be intercepted, and allocation counters are too noisy to be a gate.
    // What IS checkable, and is the actual defect, is that the named constructor no longer
    // routes through withDriver() -- the wither whose clone-per-call is correct for a wither
    // and wrong for building a 29-entry table.
    $method = new ReflectionMethod(InferenceDriverRegistry::class, 'fromArray');
    $lines = file((string) $method->getFileName());
    $body = implode('', array_slice(
        $lines,
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($body)->not->toContain('withDriver(')
        ->and($body)->toContain('toDriverFactory(');
})->group('driver-registry');

it('keeps withDriver immutable and still accepts both a class-string and a callable', function () {
    // The public contract must not narrow to callable just because fromArray stopped using it.
    $base = InferenceDriverRegistry::make();
    $viaClass = $base->withDriver('by-class', FakeInferenceDriver::class);
    $viaCallable = $viaClass->withDriver('by-callable', fn($config, $httpClient, $events) => new FakeInferenceDriver());

    expect($base->driverNames())->toBe([])
        ->and($viaClass->has('by-class'))->toBeTrue()
        ->and($viaClass->has('by-callable'))->toBeFalse()
        ->and($viaCallable->has('by-class'))->toBeTrue()
        ->and($viaCallable->has('by-callable'))->toBeTrue();
})->group('driver-registry');

it('resolves every bundled driver name to a real driver', function () {
    $registry = BundledInferenceDrivers::registry();
    $names = $registry->driverNames();

    // Pins the table size so a silently dropped entry is a failure, not a smaller pass.
    expect($names)->toHaveCount(29);

    $httpClient = (new HttpClientBuilder())->create();
    $events = new EventDispatcher();
    foreach ($names as $name) {
        $driver = $registry->makeDriver(
            name: $name,
            config: new LLMConfig(driver: $name, model: 'test-model'),
            httpClient: $httpClient,
            events: $events,
        );
        expect($driver)->toBeInstanceOf(CanProcessInferenceRequest::class);
    }
})->group('driver-registry');

it('keeps the four aliases that share one OpenAI-compatible behaviour', function () {
    // These are the easy ones to lose: four distinct names mapping to one behaviour. A map
    // built by folding a wither and a map built directly disagree about nothing here -- but a
    // hand-edited table could silently drop one.
    //
    // They shared OpenAICompatibleDriver until instructor-eexl.9 and share one
    // InferenceDriverSpec now. Asserting the class would only say they are all spec-driven,
    // which every collapsed provider is; asserting equal capabilities says they are the same
    // provider, which is the property the aliases exist to guarantee. The requests they emit
    // are pinned byte-for-byte in DriverGoldenRequestTest's equivalence classes.
    $registry = BundledInferenceDrivers::registry();
    $httpClient = (new HttpClientBuilder())->create();
    $events = new EventDispatcher();

    $capabilities = [];
    foreach (['moonshot', 'ollama', 'openai-compatible', 'together'] as $alias) {
        $driver = $registry->makeDriver(
            name: $alias,
            config: new LLMConfig(driver: $alias, model: 'test-model'),
            httpClient: $httpClient,
            events: $events,
        );
        expect($driver)->toBeInstanceOf(SpecifiedInferenceDriver::class);
        $capabilities[$alias] = $driver->capabilities();
    }

    expect(array_unique(array_map(serialize(...), $capabilities)))->toHaveCount(1);
})->group('driver-registry');
