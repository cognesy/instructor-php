<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\FindReducer;
use Cognesy\Stream\Transducers\Map;
use Cognesy\Stream\Transformation;

test('FindReducer finds first matching element', function () {
    $reducer = new FindReducer(fn($x) => $x > 3);
    $items = new ArrayIterator([1, 2, 3, 4, 5, 6]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe(4);
});

test('FindReducer returns default when no match found', function () {
    $reducer = new FindReducer(fn($x) => $x > 10, default: -1);
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe(-1);
});

test('FindReducer returns null by default when no match', function () {
    $reducer = new FindReducer(fn($x) => $x > 10);
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBeNull();
});

test('FindReducer with empty collection returns default', function () {
    $reducer = new FindReducer(fn($x) => true, default: 'empty');
    $items = new ArrayIterator([]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe('empty');
});

test('FindReducer finds object by property', function () {
    $reducer = new FindReducer(fn($x) => $x->id === 3);
    $items = new ArrayIterator([
        (object)['id' => 1, 'name' => 'Alice'],
        (object)['id' => 2, 'name' => 'Bob'],
        (object)['id' => 3, 'name' => 'Carol'],
        (object)['id' => 4, 'name' => 'Dave'],
    ]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result->name)->toBe('Carol');
});

test('FindReducer stops early and does not process remaining elements', function () {
    $processed = [];
    $reducer = new FindReducer(fn($x) => $x > 3);
    $items = new ArrayIterator([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline
        ->through(new Map(function($x) use (&$processed) {
            $processed[] = $x;
            return $x;
        }))
        ->executeOn($items);

    expect($result)->toBe(4);
    // Should only process up to 4, not all 9 elements
    expect($processed)->toBe([1, 2, 3, 4]);
});
