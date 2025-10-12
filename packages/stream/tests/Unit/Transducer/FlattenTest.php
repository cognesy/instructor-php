<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transducers\Flatten;

test('flattens one level by default', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Flatten();
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, [1, 2]);
    $acc = $wrappedReducer->step($acc, [3, 4]);
    $acc = $wrappedReducer->step($acc, 5);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2, 3, 4, 5]);
});

test('flattens nested arrays to specified depth', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Flatten(2);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, [1, [2, 3]]);
    $acc = $wrappedReducer->step($acc, [[4, 5], 6]);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2, 3, 4, 5, 6]);
});

test('does not flatten beyond specified depth', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Flatten(1);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, [1, [2, 3]]);
    $acc = $wrappedReducer->step($acc, [[4, 5], 6]);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, [2, 3], [4, 5], 6]);
});

test('handles depth of zero', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Flatten(0);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, [1, 2]);
    $acc = $wrappedReducer->step($acc, [3, 4]);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([[1, 2], [3, 4]]);
});

test('flattens deeply nested arrays', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Flatten(3);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, [1, [2, [3, 4]]]);
    $acc = $wrappedReducer->step($acc, [[[5]], 6]);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2, 3, 4, 5, 6]);
});

test('handles non-iterable values', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Flatten();
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2, 3]);
});

test('handles empty arrays', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Flatten();
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, []);
    $acc = $wrappedReducer->step($acc, [1, 2]);
    $acc = $wrappedReducer->step($acc, []);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2]);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $transducer = new Flatten();
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, [1, 2]);
    $acc = $wrappedReducer->step($acc, [3, 4]);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(100);
});
