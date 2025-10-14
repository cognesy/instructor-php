<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transform\Limit\Transducers\DropWhile;

test('drops elements while predicate is true', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new DropWhile(fn($x) => $x < 3);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $acc = $wrappedReducer->step($acc, 4);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([3, 4]);
});

test('keeps all elements when predicate is false from start', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new DropWhile(fn($x) => false);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2]);
});

test('drops all when predicate is always true', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new DropWhile(fn($x) => true);
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
    $transducer = new DropWhile(fn($x) => $x < 5);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 3);
    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(70);
});
