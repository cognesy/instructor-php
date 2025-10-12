<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\FirstReducer;
use Cognesy\Stream\Transducers\Filter;
use Cognesy\Stream\Transducers\Map;
use Cognesy\Stream\Transformation;

test('FirstReducer returns first element', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe(1);
});

test('FirstReducer returns default when collection is empty', function () {
    $reducer = new FirstReducer(default: 'empty');
    $items = new ArrayIterator([]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe('empty');
});

test('FirstReducer returns null by default when empty', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBeNull();
});

test('FirstReducer stops early and does not process remaining', function () {
    $processed = [];
    $reducer = new FirstReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline
        ->through(new Map(function($x) use (&$processed) {
            $processed[] = $x;
            return $x;
        }))
        ->executeOn($items);

    expect($result)->toBe(1);
    expect($processed)->toBe([1]); // Only first element processed
});

test('FirstReducer works with transducers', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline
        ->through(new Filter(fn($x) => $x > 2))
        ->executeOn($items);

    expect($result)->toBe(3); // First element after filter
});

test('FirstReducer with object returns single object', function () {
    $reducer = new FirstReducer();
    $items = new ArrayIterator([
        (object)['id' => 1],
        (object)['id' => 2],
        (object)['id' => 3],
    ]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBeObject();
    expect($result->id)->toBe(1);
});
