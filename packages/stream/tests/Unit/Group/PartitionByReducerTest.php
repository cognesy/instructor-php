<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transform\Group\Decorators\PartitionByReducer;

test('delegates init to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new PartitionByReducer($innerReducer, fn($x) => $x % 2);

    $result = $reducer->init();

    expect($result)->toBe([]);
});

test('partitions by group key', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new PartitionByReducer($innerReducer, fn($x) => $x < 3);

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
    $reducer = new PartitionByReducer($innerReducer, fn($x) => $x);

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
    $reducer = new PartitionByReducer($innerReducer, fn($x) => $x > 5);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1, 2]]);
});
