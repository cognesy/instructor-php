<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\MaxReducer;
use Cognesy\Stream\Transducers\Map;
use Cognesy\Stream\Transformation;

test('MaxReducer finds maximum value', function () {
    $reducer = new MaxReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([5, 2, 8, 1, 9, 3]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(9);
});

test('MaxReducer with empty collection returns null', function () {
    $reducer = new MaxReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBeNull();
});

test('MaxReducer with key function', function () {
    $reducer = new MaxReducer(fn($x) => $x->age);
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([
        (object)['name' => 'Alice', 'age' => 30],
        (object)['name' => 'Bob', 'age' => 25],
        (object)['name' => 'Carol', 'age' => 35],
    ]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(35);
});

test('MaxReducer with single element', function () {
    $reducer = new MaxReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([42]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(42);
});

test('MaxReducer works with transducers', function () {
    $reducer = new MaxReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->executeOn($items);

    expect($result)->toBe(10); // max of [2, 4, 6, 8, 10]
});
