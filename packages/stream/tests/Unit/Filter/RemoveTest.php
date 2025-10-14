<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transform\Filter\Transducers\Remove;

test('removes values when predicate is true', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Remove(fn($x) => $x % 2 === 0);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $acc = $wrappedReducer->step($acc, 4);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 3]);
});

test('removes nothing when predicate is always false', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Remove(fn($x) => false);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2]);
});

test('removes everything when predicate is always true', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Remove(fn($x) => true);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([]);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $transducer = new Remove(fn($x) => $x > 10);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 15);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(80);
});
