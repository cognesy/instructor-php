<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\Stats\SumReducer;
use Cognesy\Stream\Transform\Filter\Transducers\Filter;
use Cognesy\Stream\Transformation;

test('SumReducer sums numeric values', function () {
    $reducer = new SumReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe(15);
});

test('SumReducer with empty collection returns 0', function () {
    $reducer = new SumReducer();
    $items = new ArrayIterator([]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe(0);
});

test('SumReducer with mapper function', function () {
    $reducer = new SumReducer(fn($x) => $x->value);
    $items = new ArrayIterator([
        (object)['value' => 10],
        (object)['value' => 20],
        (object)['value' => 30],
    ]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe(60);
});

test('SumReducer works with transducers', function () {
    $reducer = new SumReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5, 6]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline
        ->through(new Filter(fn($x) => $x % 2 === 0))
        ->executeOn($items);

    expect($result)->toBe(12); // 2 + 4 + 6
});
