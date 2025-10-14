<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Support\Reduced;
use Cognesy\Stream\Transform\Limit\Transducers\TakeN;

test('takes first N elements', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new TakeN(3);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2, 3]);
});

test('returns Reduced after N elements', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new TakeN(2);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->step($acc, 3);

    expect($result)->toBeInstanceOf(Reduced::class)
        ->and($result->value())->toBe([1, 2]);
});

test('handles take zero', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new TakeN(0);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $result = $wrappedReducer->step($acc, 1);

    expect($result)->toBeInstanceOf(Reduced::class)
        ->and($result->value())->toBe([]);
});

test('throws exception for negative amount', function () {
    expect(fn() => new TakeN(-1))
        ->toThrow(InvalidArgumentException::class);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $transducer = new TakeN(2);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(80);
});
