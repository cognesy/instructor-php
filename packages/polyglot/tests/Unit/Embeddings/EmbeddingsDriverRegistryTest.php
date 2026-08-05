<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Creation\BundledEmbeddingsDrivers;
use Cognesy\Polyglot\Embeddings\Creation\EmbeddingsDriverRegistry;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;

/**
 * Behaviour pins for the embeddings driver registry (instructor-eexl.21).
 *
 * These were written BEFORE the fromArray()/memoization change and pass against the
 * pre-refactor code. That is the point: they describe what must NOT change, so they can only
 * be evidence if they were green first. The two assertions that describe the change itself
 * live in EmbeddingsBundledRegistryMemoTest.
 *
 * The registry had no test of its own before this task -- it was only ever exercised
 * incidentally, through EmbeddingsSensitiveEventPayloadRedactionRegressionTest.
 */

it('is immutable when adding and removing custom drivers', function () {
    $registry = EmbeddingsDriverRegistry::make();
    $extended = $registry->withDriver('custom-a', fn($config, $httpClient, $events) => new FakeEmbeddingsDriver());
    $reduced = $extended->withoutDriver('custom-a');

    expect($registry->driverNames())->toBe([])
        ->and($extended->has('custom-a'))->toBeTrue()
        ->and($reduced->has('custom-a'))->toBeFalse();
})->group('driver-registry');

it('still accepts both a class-string and a callable', function () {
    // The public contract must not narrow to callable just because fromArray stops using
    // withDriver(). fromArray() is the only in-package caller that passes class-strings, so
    // without this the string branch of toDriverFactory() would lose its only coverage.
    $base = EmbeddingsDriverRegistry::make();
    $viaClass = $base->withDriver('by-class', FakeEmbeddingsDriver::class);
    $viaCallable = $viaClass->withDriver('by-callable', fn($config, $httpClient, $events) => new FakeEmbeddingsDriver());

    expect($base->driverNames())->toBe([])
        ->and($viaClass->has('by-class'))->toBeTrue()
        ->and($viaClass->has('by-callable'))->toBeFalse()
        ->and($viaCallable->has('by-class'))->toBeTrue()
        ->and($viaCallable->has('by-callable'))->toBeTrue();
})->group('driver-registry');

it('does not leak custom drivers between registry instances', function () {
    $custom = EmbeddingsDriverRegistry::make()->withDriver(
        'custom-isolated',
        fn($config, $httpClient, $events) => new FakeEmbeddingsDriver(),
    );

    expect($custom->has('custom-isolated'))->toBeTrue()
        ->and(EmbeddingsDriverRegistry::make()->has('custom-isolated'))->toBeFalse();
})->group('driver-registry');

it('rejects a factory that does not return a vectorization driver', function () {
    $registry = EmbeddingsDriverRegistry::make()
        ->withDriver('bad-callable', fn($config, $httpClient, $events) => new stdClass())
        ->withDriver('bad-class', stdClass::class);

    $make = fn(string $name) => $registry->makeDriver(
        name: $name,
        config: new EmbeddingsConfig(driver: $name, model: 'test-model'),
        httpClient: (new HttpClientBuilder())->create(),
        events: new EventDispatcher(),
    );

    expect(fn() => $make('bad-callable'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $make('bad-class'))->toThrow(InvalidArgumentException::class);
})->group('driver-registry');

it('throws a named error for an unregistered driver', function () {
    expect(fn() => EmbeddingsDriverRegistry::make()->makeDriver(
        name: 'nope',
        config: new EmbeddingsConfig(driver: 'nope', model: 'test-model'),
        httpClient: (new HttpClientBuilder())->create(),
        events: new EventDispatcher(),
    ))->toThrow(InvalidArgumentException::class, 'missing embeddings driver: nope');
})->group('driver-registry');

it('resolves every bundled driver name to a real driver', function () {
    $registry = BundledEmbeddingsDrivers::registry();
    $names = $registry->driverNames();

    // Order and count are both pinned: fromArray() is about to stop folding the wither, and a
    // silently dropped or reordered entry would otherwise read as a smaller pass.
    expect($names)->toBe(['azure', 'cohere', 'gemini', 'jina', 'mistral', 'openai', 'ollama']);

    $httpClient = (new HttpClientBuilder())->create();
    $events = new EventDispatcher();
    foreach ($names as $name) {
        expect($registry->makeDriver(
            name: $name,
            config: new EmbeddingsConfig(driver: $name, model: 'test-model'),
            httpClient: $httpClient,
            events: $events,
        ))->toBeInstanceOf(CanHandleVectorization::class);
    }
})->group('driver-registry');

it('keeps the three names that share one OpenAI driver', function () {
    // mistral, openai and ollama all map to the same class. Three names, one behaviour --
    // the entries most likely to be lost when a table is rebuilt by hand.
    $registry = BundledEmbeddingsDrivers::registry();
    $httpClient = (new HttpClientBuilder())->create();
    $events = new EventDispatcher();

    $classes = [];
    foreach (['mistral', 'openai', 'ollama'] as $name) {
        $classes[] = $registry->makeDriver(
            name: $name,
            config: new EmbeddingsConfig(driver: $name, model: 'test-model'),
            httpClient: $httpClient,
            events: $events,
        )::class;
    }

    expect(array_unique($classes))->toHaveCount(1);
})->group('driver-registry');
