<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transform\Limit\Transducers\DropFirst;

test('drops first N elements', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new DropFirst(2);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $acc = $wrappedReducer->step($acc, 4);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([3, 4]);
});

test('handles drop zero', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new DropFirst(0);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2]);
});

test('drops all when N is greater than input size', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new DropFirst(10);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([]);
});

test('throws exception for negative amount', function () {
    expect(fn() => new DropFirst(-1))
        ->toThrow(InvalidArgumentException::class);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $transducer = new DropFirst(1);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(30);
});
