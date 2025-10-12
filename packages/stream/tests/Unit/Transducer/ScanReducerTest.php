<?php declare(strict_types=1);

use Cognesy\Stream\Decorators\ScanReducer;
use Cognesy\Stream\Support\CallableReducer;

test('emits intermediate accumulations', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc + $val, 0);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $result = $reducer->complete($acc);

    expect($result)->toBe([1, 3, 6]);
});

test('tracks running sum', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc + $val, 0);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 5);
    $acc = $reducer->step($acc, 10);
    $acc = $reducer->step($acc, 15);
    $result = $reducer->complete($acc);

    expect($result)->toBe([5, 15, 30]);
});

test('tracks running product', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc * $val, 1);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 3);
    $acc = $reducer->step($acc, 4);
    $result = $reducer->complete($acc);

    expect($result)->toBe([2, 6, 24]);
});

test('tracks string concatenation', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc . $val, '');

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 'a');
    $acc = $reducer->step($acc, 'b');
    $acc = $reducer->step($acc, 'c');
    $result = $reducer->complete($acc);

    expect($result)->toBe(['a', 'ab', 'abc']);
});

test('uses initial scan value', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc + $val, 100);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $result = $reducer->complete($acc);

    expect($result)->toBe([101, 103]);
});

test('handles empty input', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc + $val, 0);

    $acc = $reducer->init();
    $result = $reducer->complete($acc);

    expect($result)->toBe([]);
});

test('delegates init to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => ['start']
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc + $val, 0);

    $result = $reducer->init();

    expect($result)->toBe(['start']);
});

test('delegates complete to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $reducer = new ScanReducer($innerReducer, fn($acc, $val) => $acc + $val, 0);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);  // scan: 0+1=1, inner: 0+1=1
    $acc = $reducer->step($acc, 2);  // scan: 1+2=3, inner: 1+3=4
    $result = $reducer->complete($acc);  // 4*10=40

    expect($result)->toBe(40);
});
