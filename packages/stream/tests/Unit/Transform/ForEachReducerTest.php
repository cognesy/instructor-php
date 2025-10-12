<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\ForEachReducer;
use Cognesy\Stream\Transformation;

test('ForEachReducer executes side effect for each element', function () {
    $results = [];
    $reducer = new ForEachReducer(function ($x) use (&$results) {
        $results[] = $x * 2;
    });

    $count = (new Transformation([], $reducer))->executeOn([1, 2, 3]);

    expect($results)->toBe([2, 4, 6]);
    expect($count)->toBe(3);
});

test('ForEachReducer returns count of processed elements', function () {
    $reducer = new ForEachReducer(fn($x) => null);

    $count = (new Transformation([], $reducer))->executeOn([1, 2, 3, 4, 5]);

    expect($count)->toBe(5);
});

test('ForEachReducer with empty collection returns zero', function () {
    $reducer = new ForEachReducer(fn($x) => null);

    $count = (new Transformation([], $reducer))->executeOn([]);

    expect($count)->toBe(0);
});

test('ForEachReducer side effects execute in order', function () {
    $results = [];
    $reducer = new ForEachReducer(function ($x) use (&$results) {
        $results[] = $x;
    });

    (new Transformation([], $reducer))->executeOn(['a', 'b', 'c']);

    expect($results)->toBe(['a', 'b', 'c']);
});

test('ForEachReducer can accumulate external state', function () {
    $sum = 0;
    $reducer = new ForEachReducer(function ($x) use (&$sum) {
        $sum += $x;
    });

    (new Transformation([], $reducer))->executeOn([10, 20, 30]);

    expect($sum)->toBe(60);
});
