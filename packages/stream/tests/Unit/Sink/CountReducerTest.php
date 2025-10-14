<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\Stats\CountReducer;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

test('CountReducer counts all elements', function () {
    $reducer = new CountReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(5);
});

test('CountReducer with empty collection returns 0', function () {
    $reducer = new CountReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(0);
});

test('CountReducer with predicate counts matching elements', function () {
    $reducer = new CountReducer(fn($x) => $x > 3);
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5, 6]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(3); // 4, 5, 6
});

test('CountReducer with predicate for objects', function () {
    $reducer = new CountReducer(fn($x) => $x->active);
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([
        (object)['active' => true],
        (object)['active' => false],
        (object)['active' => true],
        (object)['active' => true],
    ]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(3);
});

test('CountReducer works with transducers', function () {
    $reducer = new CountReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->executeOn($items);

    expect($result)->toBe(5);
});
