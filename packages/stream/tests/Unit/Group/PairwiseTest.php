<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\ToArrayReducer;
use Cognesy\Stream\Transform\Group\Transducers\Pairwise;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

test('Pairwise creates overlapping pairs', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new Pairwise())
        ->executeOn($items);

    expect($result)->toBe([
        [1, 2],
        [2, 3],
        [3, 4],
        [4, 5],
    ]);
});

test('Pairwise with insufficient elements returns empty', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1]);
    $result = $pipeline
        ->through(new Pairwise())
        ->executeOn($items);

    expect($result)->toBe([]);
});

test('Pairwise with exactly two elements', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2]);
    $result = $pipeline
        ->through(new Pairwise())
        ->executeOn($items);

    expect($result)->toBe([
        [1, 2],
    ]);
});

test('Pairwise with empty collection', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([]);
    $result = $pipeline
        ->through(new Pairwise())
        ->executeOn($items);

    expect($result)->toBe([]);
});

test('Pairwise works with other transducers', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4]);
    $result = $pipeline
        ->through(new Map(fn($x) => $x * 10))
        ->through(new Pairwise())
        ->executeOn($items);

    expect($result)->toBe([
        [10, 20],
        [20, 30],
        [30, 40],
    ]);
});

test('Pairwise can compute differences', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([10, 15, 18, 25, 30]);
    $result = $pipeline
        ->through(new Pairwise())
        ->through(new Map(fn($pair) => $pair[1] - $pair[0]))
        ->executeOn($items);

    expect($result)->toBe([5, 3, 7, 5]);
});
