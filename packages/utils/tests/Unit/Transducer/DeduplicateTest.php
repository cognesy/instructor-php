<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\CallableReducer;
use Cognesy\Utils\Transducer\Transducers\Deduplicate;

test('removes consecutive duplicates via transducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Deduplicate();
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 1);
    $acc = $wrappedReducer->step($acc, 2);
    $acc = $wrappedReducer->step($acc, 3);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([1, 2, 3]);
});

test('uses key function in transducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $transducer = new Deduplicate(fn($x) => $x['type']);
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    $acc = $wrappedReducer->step($acc, ['type' => 'A', 'value' => 1]);
    $acc = $wrappedReducer->step($acc, ['type' => 'A', 'value' => 2]);
    $acc = $wrappedReducer->step($acc, ['type' => 'B', 'value' => 3]);
    $acc = $wrappedReducer->step($acc, ['type' => 'B', 'value' => 4]);
    $acc = $wrappedReducer->step($acc, ['type' => 'A', 'value' => 5]);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe([
        ['type' => 'A', 'value' => 1],
        ['type' => 'B', 'value' => 3],
        ['type' => 'A', 'value' => 5],
    ]);
});

test('delegates init and complete to wrapped reducer', function () {
    $reducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 2,
        initFn: fn() => 0
    );
    $transducer = new Deduplicate();
    $wrappedReducer = $transducer($reducer);

    $acc = $wrappedReducer->init();
    expect($acc)->toBe(0);

    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 5);
    $acc = $wrappedReducer->step($acc, 3);
    $result = $wrappedReducer->complete($acc);

    expect($result)->toBe(16);
});
