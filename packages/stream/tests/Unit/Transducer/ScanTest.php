<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transducers\Scan;

test('emits running totals via transducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Scan(fn($acc, $val) => $acc + $val, 0);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $acc = $wrappedReducer->step($acc, 4);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 3, 6, 10]);
});

test('tracks maximum value seen so far', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Scan(fn($acc, $val) => max($acc, $val), PHP_INT_MIN);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 8);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([5, 5, 8, 8]);
});

test('builds array of elements seen so far', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Scan(fn($acc, $val) => [...$acc, $val], []);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 'a');
    $acc = $wrappedReducer->step($acc, 'b');
    $acc = $wrappedReducer->step($acc, 'c');
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([
        ['a'],
        ['a', 'b'],
        ['a', 'b', 'c'],
    ]);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 2,
        initFn: fn() => 0
    );
    $transducer = new Scan(fn($acc, $val) => $acc + $val, 0);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 1);  // scan: 0+1=1, inner: 0+1=1
    $acc = $wrappedReducer->step($acc, 2);  // scan: 1+2=3, inner: 1+3=4
    $result = $wrappedReducer->complete($acc);  // 4*2=8

    expect($result)->toBe(8);
});
