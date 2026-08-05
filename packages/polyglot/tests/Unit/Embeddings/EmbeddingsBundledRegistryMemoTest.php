<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Creation\BundledEmbeddingsDrivers;
use Cognesy\Polyglot\Embeddings\Creation\EmbeddingsDriverRegistry;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;

/**
 * The two assertions that describe the instructor-eexl.21 change itself, kept apart from the
 * behaviour pins in EmbeddingsDriverRegistryTest (which were green before it).
 */

it('returns the same bundled registry instance on every call', function () {
    // The bundled table is a compile-time constant. Rebuilding it per implicitly-constructed
    // EmbeddingsRuntime cost ~2.0us and 7 registry clones.
    expect(BundledEmbeddingsDrivers::registry())->toBe(BundledEmbeddingsDrivers::registry());
})->group('driver-registry');

it('does not let a derived registry affect the shared one', function () {
    // The entire safety argument for sharing one instance: the registry is immutable, so a
    // caller customising the bundled registry gets a copy and cannot reach the memoized
    // object. Asserted rather than left as a comment, because the failure mode is a shared
    // mutable singleton leaking between callers.
    $derived = BundledEmbeddingsDrivers::registry()
        ->withoutDriver('openai')
        ->withDriver('custom-x', fn($config, $httpClient, $events) => new FakeEmbeddingsDriver());

    expect($derived->has('openai'))->toBeFalse()
        ->and($derived->has('custom-x'))->toBeTrue()
        ->and(BundledEmbeddingsDrivers::registry()->has('openai'))->toBeTrue()
        ->and(BundledEmbeddingsDrivers::registry()->has('custom-x'))->toBeFalse();
})->group('driver-registry');

it('builds fromArray without folding the public wither over the map', function () {
    // "fromArray() performs zero clones" cannot be asserted by counting: the class is final,
    // so __clone cannot be intercepted, and allocation counters are too noisy to be a gate.
    // What IS checkable, and is the actual defect, is that the named constructor no longer
    // routes through withDriver() -- the wither whose clone-per-call is correct for a wither
    // and wrong for building a table.
    $method = new ReflectionMethod(EmbeddingsDriverRegistry::class, 'fromArray');
    $lines = file((string) $method->getFileName());
    $body = implode('', array_slice(
        $lines,
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($body)->not->toContain('withDriver(')
        ->and($body)->toContain('toDriverFactory(');
})->group('driver-registry');
