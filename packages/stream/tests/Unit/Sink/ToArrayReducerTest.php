<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\ToArrayReducer;
use Cognesy\Stream\Transform\Map\Transducers\Map;
use Cognesy\Stream\Transformation;

test('ToArrayReducer collects items into array', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe([1, 2, 3]);
});

test('ToArrayReducer works with transducers', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4]);
    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->executeOn($items);

    expect($result)->toBe([2, 4, 6, 8]);
});

