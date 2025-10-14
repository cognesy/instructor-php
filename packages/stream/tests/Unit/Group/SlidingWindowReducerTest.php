<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transform\Group\Decorators\SlidingWindowReducer;

test('delegates init to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new SlidingWindowReducer($innerReducer, 2);

    $result = $reducer->init();

    expect($result)->toBe([]);
});

test('emits window when size is reached', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new SlidingWindowReducer($innerReducer, 3);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $acc = $reducer->step($acc, 4);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1, 2, 3], [2, 3, 4]]);
});

test('slides window by one element', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new SlidingWindowReducer($innerReducer, 2);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $result = $reducer->complete($acc);

    expect($result)->toBe([[1, 2], [2, 3]]);
});

test('does not emit until window size is reached', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new SlidingWindowReducer($innerReducer, 3);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $result = $reducer->complete($acc);

    expect($result)->toBe([]);
});

test('throws exception for non-positive window size', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc,
        completeFn: null,
        initFn: fn() => []
    );

    expect(fn() => new SlidingWindowReducer($innerReducer, 0))
        ->toThrow(InvalidArgumentException::class);
});
