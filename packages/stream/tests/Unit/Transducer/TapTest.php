<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transducers\Tap;

test('executes side effect for each value', function () {
    $sideEffect = [];
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val * 2],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Tap(function ($x) use (&$sideEffect) {
        $sideEffect[] = $x;
    });
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([2, 4, 6])
        ->and($sideEffect)->toBe([1, 2, 3]);
});

test('does not modify values', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Tap(fn($x) => $x * 100);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2]);
});

test('side effect runs before reduction step', function () {
    $log = [];
    $reducer = new CallableReducer(
        stepFn: function ($acc, $val) use (&$log) {
            $log[] = "step:$val";
            return [...$acc, $val];
        },
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Tap(function ($x) use (&$log) {
        $log[] = "tap:$x";
    });
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $wrappedReducer->complete($acc);

    expect($log)->toBe(['tap:1', 'step:1']);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $transducer = new Tap(fn($x) => null);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(80);
});
