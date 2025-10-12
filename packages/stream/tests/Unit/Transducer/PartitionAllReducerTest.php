<?php declare(strict_types=1);

use Cognesy\Stream\Decorators\ChunkReducer;
use Cognesy\Stream\Support\CallableReducer;

test('delegates init to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ChunkReducer(2, $innerReducer);

    $result = $reducer->init();

    expect($result)->toBe([]);
});

test('partitions into chunks of specified size', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ChunkReducer(2, $innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $acc = $reducer->step($acc, 4);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1, 2], [3, 4]]);
});

test('flushes remaining items on complete', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ChunkReducer(3, $innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $acc = $reducer->step($acc, 4);
    $acc = $reducer->step($acc, 5);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1, 2, 3], [4, 5]]);
});

test('handles single item partition', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ChunkReducer(2, $innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1]]);
});
