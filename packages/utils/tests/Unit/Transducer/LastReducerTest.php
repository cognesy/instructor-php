<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\Sinks\LastReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\Filter;
use Cognesy\Utils\Transducer\Transducers\Map;

test('LastReducer returns last element', function () {
    $reducer = new LastReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe(5);
});

test('LastReducer returns default when collection is empty', function () {
    $reducer = new LastReducer(default: 'empty');
    $items = new ArrayIterator([]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe('empty');
});

test('LastReducer returns null by default when empty', function () {
    $reducer = new LastReducer();
    $items = new ArrayIterator([]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBeNull();
});

test('LastReducer with single element', function () {
    $reducer = new LastReducer();
    $items = new ArrayIterator([42]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe(42);
});

test('LastReducer works with transducers', function () {
    $reducer = new LastReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline
        ->through(new Filter(fn($x) => $x < 4))
        ->applyTo($items);

    expect($result)->toBe(3); // Last element after filter
});

test('LastReducer with Map transducer', function () {
    $reducer = new LastReducer();
    $items = new ArrayIterator([1, 2, 3]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline
        ->through(new Map(fn($x) => $x * 10))
        ->applyTo($items);

    expect($result)->toBe(30); // Last of [10, 20, 30]
});

test('LastReducer with object returns single object', function () {
    $reducer = new LastReducer();
    $items = new ArrayIterator([
        (object)['id' => 1],
        (object)['id' => 2],
        (object)['id' => 3],
    ]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBeObject();
    expect($result->id)->toBe(3);
});
