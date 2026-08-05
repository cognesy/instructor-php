<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Creation\BundledHttpDrivers;
use Cognesy\Http\Creation\HttpDriverRegistry;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

/**
 * The two assertions that describe the instructor-eexl.21 change itself, kept apart from the
 * behaviour pins in HttpDriverRegistryTest (which were green before it).
 */

it('returns the same bundled registry instance on every call', function () {
    // Three entries, so the absolute win is small -- but this registry is resolved by
    // HttpClientRuntime on every implicitly-constructed client, which is also every
    // implicitly-constructed inference runtime.
    expect(BundledHttpDrivers::registry())->toBe(BundledHttpDrivers::registry());
})->group('driver-registry');

it('does not let a derived registry affect the shared one', function () {
    // The entire safety argument for sharing one instance. docs/9-1-custom-clients.md shows
    // exactly this call shape, so a caller reaching the shared object is a documented path,
    // not a hypothetical one.
    $derived = BundledHttpDrivers::registry()
        ->withoutDriver('curl')
        ->withDriver('custom-x', fn($config, $events, $clientInstance) => new class implements CanHandleHttpRequest {
            #[\Override]
            public function handle(HttpRequest $request): HttpResponse {
                return HttpResponse::sync(statusCode: 200, headers: [], body: '{}');
            }
        });

    expect($derived->has('curl'))->toBeFalse()
        ->and($derived->has('custom-x'))->toBeTrue()
        ->and(BundledHttpDrivers::registry()->has('curl'))->toBeTrue()
        ->and(BundledHttpDrivers::registry()->has('custom-x'))->toBeFalse();
})->group('driver-registry');

it('builds fromArray without folding the public wither over the map', function () {
    // See EmbeddingsBundledRegistryMemoTest for why this is checked by reading the method
    // body rather than by counting clones.
    $method = new ReflectionMethod(HttpDriverRegistry::class, 'fromArray');
    $lines = file((string) $method->getFileName());
    $body = implode('', array_slice(
        $lines,
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($body)->not->toContain('withDriver(')
        ->and($body)->toContain('toDriverFactory(');
})->group('driver-registry');
