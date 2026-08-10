<?php declare(strict_types=1);

use Cognesy\Utils\Cached;

/**
 * Cached kept its resolved value in a static WeakMap keyed by $this, because the
 * properties were readonly. It then probed that map with isset(), which reports a
 * stored null as absent - WeakMap::offsetExists() does the same, so switching to it
 * would not have helped. A producer returning null was therefore re-run on EVERY
 * get(), breaking the "called only on the first access" contract, and isResolved()
 * stayed false forever. Resolved state now lives on the instance.
 */

it('runs a null-returning producer exactly once', function () {
    $calls = 0;
    $cached = Cached::from(function () use (&$calls) {
        $calls++;
        return null;
    });

    expect($cached->get())->toBeNull();
    expect($cached->get())->toBeNull();
    expect($cached->get())->toBeNull();
    expect($calls)->toBe(1);
});

it('reports a resolved null as resolved', function () {
    $cached = Cached::from(fn() => null);
    expect($cached->isResolved())->toBeFalse();
    $cached->get();
    expect($cached->isResolved())->toBeTrue();
});

it('runs the producer once for every falsy value', function (mixed $value) {
    $calls = 0;
    $cached = Cached::from(function () use (&$calls, $value) {
        $calls++;
        return $value;
    });

    expect($cached->get())->toBe($value);
    expect($cached->get())->toBe($value);
    expect($calls)->toBe(1);
    expect($cached->isResolved())->toBeTrue();
})->with([
    'null' => [null],
    'false' => [false],
    'zero' => [0],
    'empty string' => [''],
    'empty array' => [[]],
    'zero float' => [0.0],
]);

it('forwards arguments to the producer on the first call only', function () {
    $seen = [];
    $cached = Cached::from(function (...$args) use (&$seen) {
        $seen[] = $args;
        return null;
    });

    $cached->get('a', 'b');
    $cached->get('ignored');

    expect($seen)->toBe([['a', 'b']]);
});

it('caches independently per instance', function () {
    $calls = 0;
    $make = function () use (&$calls) {
        return Cached::from(function () use (&$calls) {
            $calls++;
            return null;
        });
    };

    $make()->get();
    $make()->get();

    expect($calls)->toBe(2);
});

it('treats a pre-resolved null value as resolved without a producer', function () {
    $cached = Cached::fromValue(null);
    expect($cached->isResolved())->toBeTrue();
    expect($cached->get())->toBeNull();
    expect((string) $cached)->toBe('NULL');
});

it('is invokable and returns the cached value', function () {
    $cached = Cached::from(fn() => null);
    expect($cached())->toBeNull();
    expect($cached->isResolved())->toBeTrue();
});

it('renders unresolved and resolved states distinctly', function () {
    $cached = Cached::from(fn() => 'value');
    expect((string) $cached)->toBe('(unresolved)');
    $cached->get();
    expect((string) $cached)->toBe('value');
});
