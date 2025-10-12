<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\Sinks\FirstReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\Filter;
use Cognesy\Utils\Transducer\Transducers\Map;

test('FirstReducer returns first element', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe(1);
});

test('FirstReducer returns default when collection is empty', function () {
    $reducer = new FirstReducer(default: 'empty');
    $items = new ArrayIterator([]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe('empty');
});

test('FirstReducer returns null by default when empty', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBeNull();
});

test('FirstReducer stops early and does not process remaining', function () {
    $processed = [];
    $reducer = new FirstReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline
        ->through(new Map(function($x) use (&$processed) {
            $processed[] = $x;
            return $x;
        }))
        ->applyTo($items);

    expect($result)->toBe(1);
    expect($processed)->toBe([1]); // Only first element processed
});

test('FirstReducer works with transducers', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline
        ->through(new Filter(fn($x) => $x > 2))
        ->applyTo($items);

    expect($result)->toBe(3); // First element after filter
});

test('FirstReducer with object returns single object', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([
        (object)['id' => 1],
        (object)['id' => 2],
        (object)['id' => 3],
    ]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBeObject();
    expect($result->id)->toBe(1);
});
