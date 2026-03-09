<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverRegistry;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('is immutable when adding and removing custom drivers', function () {
    $registry = InferenceDriverRegistry::make();
    $extended = $registry->withDriver('custom-a', fn($config, $httpClient, $events) => new FakeInferenceDriver());
    $reduced = $extended->withoutDriver('custom-a');

    expect($registry->driverNames())->toBe([])
        ->and($extended->has('custom-a'))->toBeTrue()
        ->and($reduced->has('custom-a'))->toBeFalse();
});

it('builds registered custom drivers', function () {
    $registry = InferenceDriverRegistry::make()->withDriver(
        'custom-driver',
        fn($config, $httpClient, $events) => new FakeInferenceDriver(),
    );

    $driver = $registry->makeDriver(
        name: 'custom-driver',
        config: new LLMConfig(driver: 'custom-driver', model: 'test-model'),
        httpClient: (new HttpClientBuilder())->create(),
        events: new EventDispatcher(),
    );

    expect($driver)->toBeInstanceOf(FakeInferenceDriver::class);
});

it('does not leak custom drivers between registry instances', function () {
    $customRegistry = InferenceDriverRegistry::make()->withDriver(
        'custom-isolated',
        fn($config, $httpClient, $events) => new FakeInferenceDriver(),
    );

    expect($customRegistry->has('custom-isolated'))->toBeTrue()
        ->and(InferenceDriverRegistry::make()->has('custom-isolated'))->toBeFalse();
});

it('includes bundled drivers in the default bundled registry', function () {
    $registry = BundledInferenceDrivers::registry();

    expect($registry->has('openai'))->toBeTrue()
        ->and($registry->has('anthropic'))->toBeTrue()
        ->and($registry->has('gemini'))->toBeTrue();
});
