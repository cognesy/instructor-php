<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\Sinks\ToStringReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\Map;

test('ToStringReducer concatenates without separator by default', function () {
    $reducer = new ToStringReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->applyTo($items);

    expect($result)->toBe('123');
});

test('ToStringReducer concatenates with separator', function () {
    $reducer = new ToStringReducer(',');
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->applyTo($items);

    expect($result)->toBe('1,2,3');
});

test('ToStringReducer supports prefix and suffix', function () {
    $reducer = new ToStringReducer(', ', '[', ']');
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator(['a', 'b']);
    $result = $pipeline
        ->through(new Map(fn($x) => strtoupper($x)))
        ->applyTo($items);

    expect($result)->toBe('[A, B]');
});

