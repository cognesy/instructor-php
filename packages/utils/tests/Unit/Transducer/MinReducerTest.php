<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\Sinks\MinReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\Map;

test('MinReducer finds minimum value', function () {
    $reducer = new MinReducer();
    $items = new ArrayIterator([5, 2, 8, 1, 9, 3]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe(1);
});

test('MinReducer with empty collection returns null', function () {
    $reducer = new MinReducer();
    $items = new ArrayIterator([]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBeNull();
});

test('MinReducer with key function', function () {
    $reducer = new MinReducer(fn($x) => $x->age);
    $items = new ArrayIterator([
        (object)['name' => 'Alice', 'age' => 30],
        (object)['name' => 'Bob', 'age' => 25],
        (object)['name' => 'Carol', 'age' => 35],
    ]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe(25);
});

test('MinReducer with single element', function () {
    $reducer = new MinReducer();
    $items = new ArrayIterator([42]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe(42);
});

test('MinReducer works with transducers', function () {
    $reducer = new MinReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->applyTo($items);

    expect($result)->toBe(2); // min of [2, 4, 6, 8, 10]
});
