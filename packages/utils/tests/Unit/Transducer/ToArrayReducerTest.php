<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\Sinks\ToArrayReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\Map;

test('ToArrayReducer collects items into array', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->applyTo($items);

    expect($result)->toBe([1, 2, 3]);
});

test('ToArrayReducer works with transducers', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4]);
    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->applyTo($items);

    expect($result)->toBe([2, 4, 6, 8]);
});

