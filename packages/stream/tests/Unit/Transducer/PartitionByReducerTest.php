<?php declare(strict_types=1);

use Cognesy\Stream\Decorators\PartitionByReducer;
use Cognesy\Stream\Support\CallableReducer;

test('delegates init to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new PartitionByReducer(fn($x) => $x % 2, $innerReducer);

    $result = $reducer->init();

    expect($result)->toBe([]);
});

test('partitions by group key', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new PartitionByReducer(fn($x) => $x < 3, $innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $acc = $reducer->step($acc, 4);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1, 2], [3, 4]]);
});

test('creates new partition when group changes', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new PartitionByReducer(fn($x) => $x, $innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 'a');
    $acc = $reducer->step($acc, 'a');
    $acc = $reducer->step($acc, 'b');
    $acc = $reducer->step($acc, 'b');
    $acc = $reducer->step($acc, 'a');
    $result = $reducer->complete($acc);

    expect($result)->toBe([['a', 'a'], ['b', 'b'], ['a']]);
});

test('flushes remaining partition on complete', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new PartitionByReducer(fn($x) => $x > 5, $innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1, 2]]);
});
