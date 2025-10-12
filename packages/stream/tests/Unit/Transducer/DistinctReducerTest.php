<?php declare(strict_types=1);

use Cognesy\Stream\Decorators\DistinctReducer;
use Cognesy\Stream\Support\CallableReducer;

test('removes all duplicate values', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new DistinctReducer($innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 2);
    $acc = $reducer->step($acc, 1);
    $acc = $reducer->step($acc, 3);
    $acc = $reducer->step($acc, 2);
    $result = $reducer->complete($acc);

    expect($result)->toBe([1, 2, 3]);
});

test('removes duplicates regardless of position', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new DistinctReducer($innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 'a');
    $acc = $reducer->step($acc, 'b');
    $acc = $reducer->step($acc, 'c');
    $acc = $reducer->step($acc, 'a');
    $acc = $reducer->step($acc, 'b');
    $result = $reducer->complete($acc);

    expect($result)->toBe(['a', 'b', 'c']);
});

test('uses key function for comparison', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new DistinctReducer($innerReducer, fn($x) => $x['id']);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, ['id' => 1, 'name' => 'foo']);
    $acc = $reducer->step($acc, ['id' => 2, 'name' => 'bar']);
    $acc = $reducer->step($acc, ['id' => 1, 'name' => 'baz']);
    $acc = $reducer->step($acc, ['id' => 3, 'name' => 'qux']);
    $result = $reducer->complete($acc);

    expect($result)->toBe([
        ['id' => 1, 'name' => 'foo'],
        ['id' => 2, 'name' => 'bar'],
        ['id' => 3, 'name' => 'qux']
    ]);
});

test('handles empty input', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new DistinctReducer($innerReducer);

    $acc = $reducer->init();
    $result = $reducer->complete($acc);

    expect($result)->toBe([]);
});

test('handles single element', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => []
    );
    $reducer = new DistinctReducer($innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 42);
    $result = $reducer->complete($acc);

    expect($result)->toBe([42]);
});

test('delegates init to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => [...$acc, $val],
        completeFn: null,
        initFn: fn() => [100]
    );
    $reducer = new DistinctReducer($innerReducer);

    $result = $reducer->init();

    expect($result)->toBe([100]);
});

test('delegates complete to wrapped reducer', function () {
    $innerReducer = new CallableReducer(
        stepFn: fn($acc, $val) => $acc + $val,
        completeFn: fn($acc) => $acc * 10,
        initFn: fn() => 0
    );
    $reducer = new DistinctReducer($innerReducer);

    $acc = $reducer->init();
    $acc = $reducer->step($acc, 5);
    $acc = $reducer->step($acc, 5);
    $acc = $reducer->step($acc, 3);
    $result = $reducer->complete($acc);

    expect($result)->toBe(80);
});
