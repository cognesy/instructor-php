<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\Stats\AverageReducer;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;
use Cognesy\Stream\Transformation;

test('AverageReducer calculates average', function () {
    $reducer = new AverageReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([10, 20, 30, 40, 50]);
    $result = $pipeline->executeOn($items);

    expect($result)->toEqual(30.0);
});

test('AverageReducer with empty collection returns 0', function () {
    $reducer = new AverageReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe(0);
});

test('AverageReducer with mapper function', function () {
    $reducer = new AverageReducer(fn($x) => $x->score);
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([
        (object)['score' => 80],
        (object)['score' => 90],
        (object)['score' => 70],
    ]);
    $result = $pipeline->executeOn($items);

    expect($result)->toEqual(80.0);
});

test('AverageReducer with single element', function () {
    $reducer = new AverageReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([42]);
    $result = $pipeline->executeOn($items);

    expect($result)->toEqual(42.0);
});

test('AverageReducer works with transducers', function () {
    $reducer = new AverageReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5, 6]);
    $result = $pipeline
        ->through(new Filter(fn($x) => $x % 2 === 0))
        ->executeOn($items);

    expect($result)->toEqual(4.0); // average of [2, 4, 6]
});
