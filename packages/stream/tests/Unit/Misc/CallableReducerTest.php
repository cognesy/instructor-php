<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;

test('executes step function', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val
    );

    $result = $reducer->step(10, 5);

    expect($result)->toBe(15);
});

test('returns accumulator from complete when no complete function provided', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val
    );

    $result = $reducer->complete(42);

    expect($result)->toBe(42);
});

test('executes complete function when provided', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 2
    );

    $result = $reducer->complete(21);

    expect($result)->toBe(42);
});

test('returns null from init when no init function provided', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val
    );

    $result = $reducer->init();

    expect($result)->toBeNull();
});

test('executes init function when provided', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: null,
        initFn: fn() => []
    );

    $result = $reducer->init();

    expect($result)->toBe([]);
});

test('full reduction cycle with init', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val * 2],
        completeFn: fn($acc) => implode(',', $acc),
        initFn: fn() => []
    );

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $result = $reducer->complete($acc);

    expect($result)->toBe('2,4,6');
});
