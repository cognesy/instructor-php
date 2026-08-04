<?php declare(strict_types=1);

use Cognesy\Utils\Uuid;

/**
 * Event::__construct() calls Uuid::correlationId() for every event in the framework,
 * so it has to be cheap AND indistinguishable in shape from uuid4() to consumers that
 * only ever format or compare it.
 */

it('produces ids that pass the library uuid validator', function () {
    for ($i = 0; $i < 100; $i++) {
        expect(Uuid::isValid(Uuid::correlationId()))->toBeTrue();
    }
});

it('produces ids with the same shape as uuid4', function () {
    $correlation = Uuid::correlationId();
    $random = Uuid::uuid4();

    expect(strlen($correlation))->toBe(strlen($random))
        ->and(array_map('strlen', explode('-', $correlation)))
        ->toBe(array_map('strlen', explode('-', $random)));
});

it('does not repeat within a process', function () {
    $seen = [];
    for ($i = 0; $i < 200_000; $i++) {
        $seen[Uuid::correlationId()] = true;
    }

    expect($seen)->toHaveCount(200_000);
});

it('keeps a fixed length as the counter grows', function () {
    $first = Uuid::correlationId();
    for ($i = 0; $i < 100_000; $i++) {
        $last = Uuid::correlationId();
    }

    expect(strlen($last))->toBe(strlen($first))
        ->and(Uuid::isValid($last))->toBeTrue();
});

it('draws a new prefix after a reset', function () {
    $before = Uuid::correlationId();
    Uuid::resetCorrelationPrefix();
    $after = Uuid::correlationId();

    $prefixOf = static fn(string $id): string => implode('-', array_slice(explode('-', $id), 0, 3));

    expect($prefixOf($after))->not->toBe($prefixOf($before))
        ->and(Uuid::isValid($after))->toBeTrue();
});

it('leaves uuid4 unchanged', function () {
    $a = Uuid::uuid4();
    $b = Uuid::uuid4();

    // uuid4 draws fresh randomness per call, so consecutive values share no prefix.
    expect($a)->not->toBe($b)
        ->and(Uuid::isValid($a))->toBeTrue()
        ->and(explode('-', $a)[0])->not->toBe(explode('-', $b)[0]);
});
