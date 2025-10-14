<?php declare(strict_types=1);

use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Stream\Transform\Combine\Transducers\Interleave;

test('interleaves two collections', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Interleave([10, 20, 30]);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 10, 2, 20, 3, 30]);
});

test('interleaves three collections', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Interleave([10, 20], [100, 200]);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 10, 100, 2, 20, 200]);
});

test('handles different length collections', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Interleave([10, 20]);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $acc = $wrappedReducer->step($acc, 4);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 10, 2, 20, 3, 4]);
});

test('handles main stream longer than interleaved', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Interleave([10]);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 10, 2, 3]);
});

test('handles empty interleaved collection', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Interleave([]);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2]);
});

test('handles no interleaved collections', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Interleave();
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2]);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $transducer = new Interleave([10, 20]);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(330);
});
