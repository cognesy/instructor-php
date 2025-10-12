<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\CallableReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\Filter;
use Cognesy\Utils\Transducer\Transducers\Map;
use Cognesy\Utils\Transducer\Transducers\TakeN;

test('executes transduction with single transducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4]);
    $result = $pipeline->through(new Map(fn($x) => $x * 2))->applyTo($items);

    expect($result)->toBe([2, 4, 6, 8]);
});

test('composes multiple transducers', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->through(new Filter(fn($x) => $x > 5))
        ->applyTo($items);

    expect($result)->toBe([6, 8, 10]);
});

test('uses reducer init for initial accumulator', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: null,
        initFn: fn() => 100
    );
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->applyTo($items);

    expect($result)->toBe(106);
});

test('calls complete on reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->applyTo($items);

    expect($result)->toBe(60);
});

test('handles early termination with Reduced', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline->through(new TakeN(3))->applyTo($items);

    expect($result)->toBe([1, 2, 3]);
});

test('allows changing items with over', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->applyTo($items);

    expect($result)->toBe([2, 4, 6]);
});

test('allows changing reducer with reduceWith', function () {
    $reducer1 = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer2 = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: null,
        initFn: fn() => 0
    );
    $pipeline = new Transduce([], $reducer1);

    $items = new ArrayIterator([1, 2, 3]);
    $result = $pipeline->withSink($reducer2)->applyTo($items);

    expect($result)->toBe(6);
});
