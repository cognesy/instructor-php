<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Creation\BundledHttpDrivers;
use Cognesy\Http\Creation\HttpDriverRegistry;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

/**
 * Behaviour pins for the HTTP driver registry (instructor-eexl.21).
 *
 * Written BEFORE the fromArray()/memoization change and green against the pre-refactor code,
 * so they describe what must NOT change. The assertions that describe the change itself live
 * in BundledHttpDriversMemoTest.
 *
 * This registry had no test of its own at all -- not even an incidental one. It also sits on
 * the HttpClientRuntime path, which every inference runtime construction traverses.
 */

function fakeHttpDriver(): CanHandleHttpRequest {
    return new class implements CanHandleHttpRequest {
        #[\Override]
        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::sync(statusCode: 200, headers: [], body: '{}');
        }
    };
}

it('is immutable when adding and removing custom drivers', function () {
    $registry = HttpDriverRegistry::make();
    $extended = $registry->withDriver('custom-a', fn($config, $events, $clientInstance) => fakeHttpDriver());
    $reduced = $extended->withoutDriver('custom-a');

    expect($registry->driverNames())->toBe([])
        ->and($extended->has('custom-a'))->toBeTrue()
        ->and($reduced->has('custom-a'))->toBeFalse();
})->group('driver-registry');

it('still accepts both a class-string and a callable', function () {
    // fromArray() is the only in-package caller passing class-strings. Once it stops routing
    // through withDriver(), the string branch of toDriverFactory() loses its only coverage
    // unless something like this holds it.
    $driverClass = get_class(fakeHttpDriver());
    $base = HttpDriverRegistry::make();
    $viaClass = $base->withDriver('by-class', $driverClass);
    $viaCallable = $viaClass->withDriver('by-callable', fn($config, $events, $clientInstance) => fakeHttpDriver());

    expect($base->driverNames())->toBe([])
        ->and($viaClass->has('by-class'))->toBeTrue()
        ->and($viaClass->has('by-callable'))->toBeFalse()
        ->and($viaCallable->has('by-class'))->toBeTrue()
        ->and($viaCallable->has('by-callable'))->toBeTrue();
})->group('driver-registry');

it('does not leak custom drivers between registry instances', function () {
    $custom = HttpDriverRegistry::make()->withDriver(
        'custom-isolated',
        fn($config, $events, $clientInstance) => fakeHttpDriver(),
    );

    expect($custom->has('custom-isolated'))->toBeTrue()
        ->and(HttpDriverRegistry::make()->has('custom-isolated'))->toBeFalse();
})->group('driver-registry');

it('rejects a factory that does not return an HTTP driver', function () {
    $registry = HttpDriverRegistry::make()
        ->withDriver('bad-callable', fn($config, $events, $clientInstance) => new stdClass())
        ->withDriver('bad-class', stdClass::class);

    $make = fn(string $name) => $registry->makeDriver(
        name: $name,
        config: new HttpClientConfig(driver: $name),
        events: new EventDispatcher(),
    );

    expect(fn() => $make('bad-callable'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => $make('bad-class'))->toThrow(InvalidArgumentException::class);
})->group('driver-registry');

it('throws a named error for an unregistered driver', function () {
    expect(fn() => HttpDriverRegistry::make()->makeDriver(
        name: 'nope',
        config: new HttpClientConfig(driver: 'nope'),
        events: new EventDispatcher(),
    ))->toThrow(InvalidArgumentException::class, 'Unknown HTTP driver: nope');
})->group('driver-registry');

it('resolves every bundled driver name to a real driver', function () {
    $registry = BundledHttpDrivers::registry();

    // Order and count pinned: fromArray() is about to stop folding the wither.
    expect($registry->driverNames())->toBe(['curl', 'guzzle', 'symfony']);

    $events = new EventDispatcher();
    foreach ($registry->driverNames() as $name) {
        expect($registry->makeDriver(
            name: $name,
            config: new HttpClientConfig(driver: $name),
            events: $events,
        ))->toBeInstanceOf(CanHandleHttpRequest::class);
    }
})->group('driver-registry');

it('passes the client instance through to the driver factory', function () {
    // makeDriver()'s third argument is optional and easy to drop while editing the table
    // plumbing; nothing else asserts it arrives.
    $seen = new stdClass();
    $received = null;
    $registry = HttpDriverRegistry::make()->withDriver(
        'capturing',
        function ($config, $events, $clientInstance) use (&$received) {
            $received = $clientInstance;
            return fakeHttpDriver();
        },
    );

    $registry->makeDriver(
        name: 'capturing',
        config: new HttpClientConfig(driver: 'capturing'),
        events: new EventDispatcher(),
        clientInstance: $seen,
    );

    expect($received)->toBe($seen);
})->group('driver-registry');
