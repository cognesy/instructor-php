<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\ToArrayReducer;
use Cognesy\Stream\Transform\Limit\Transducers\TakeUntil;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

test('TakeUntil takes elements until predicate is true (inclusive)', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5, 6]);
    $result = $pipeline
        ->through(new TakeUntil(fn($x) => $x === 4))
        ->executeOn($items);

    expect($result)->toBe([1, 2, 3, 4]); // Includes 4
});

test('TakeUntil with predicate never matching takes all', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new TakeUntil(fn($x) => $x > 10))
        ->executeOn($items);

    expect($result)->toBe([1, 2, 3, 4, 5]);
});

test('TakeUntil with predicate matching first element', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new TakeUntil(fn($x) => $x === 1))
        ->executeOn($items);

    expect($result)->toBe([1]); // Only first element
});

test('TakeUntil with empty collection', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([]);
    $result = $pipeline
        ->through(new TakeUntil(fn($x) => true))
        ->executeOn($items);

    expect($result)->toBe([]);
});

test('TakeUntil stops early and does not process remaining', function () {
    $processed = [];
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    $result = $pipeline
        ->through(new Map(function($x) use (&$processed) {
            $processed[] = $x;
            return $x;
        }))
        ->through(new TakeUntil(fn($x) => $x === 4))
        ->executeOn($items);

    expect($result)->toBe([1, 2, 3, 4]);
    expect($processed)->toBe([1, 2, 3, 4]); // Should not process beyond 4
});

test('TakeUntil with string delimiter', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator(['a', 'b', 'c', 'END', 'x', 'y', 'z']);
    $result = $pipeline
        ->through(new TakeUntil(fn($x) => $x === 'END'))
        ->executeOn($items);

    expect($result)->toBe(['a', 'b', 'c', 'END']);
});

test('TakeUntil is inclusive vs TakeWhile which is exclusive', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    // TakeUntil includes the matching element
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new TakeUntil(fn($x) => $x >= 3))
        ->executeOn($items);

    expect($result)->toBe([1, 2, 3]); // Includes 3
});
