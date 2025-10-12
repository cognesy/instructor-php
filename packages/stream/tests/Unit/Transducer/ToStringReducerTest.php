<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\ToStringReducer;
use Cognesy\Stream\Transducers\Map;
use Cognesy\Stream\Transformation;

test('ToStringReducer concatenates without separator by default', function () {
    $reducer = new ToStringReducer();
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe('123');
});

test('ToStringReducer concatenates with separator', function () {
    $reducer = new ToStringReducer(',');
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->executeOn($items);

    expect($result)->toBe('1,2,3');
});

test('ToStringReducer supports prefix and suffix', function () {
    $reducer = new ToStringReducer(', ', '[', ']');
    $pipeline = new Transformation([], $reducer);

    $items = new ArrayIterator(['a', 'b']);
    $result = $pipeline
        ->through(new Map(fn($x) => strtoupper($x)))
        ->executeOn($items);

    expect($result)->toBe('[A, B]');
});

