<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\Bool\MatchesAllReducer;
use Cognesy\Stream\Transformation;

test('MatchesAllReducer returns true when all elements match', function () {
    $reducer = new MatchesAllReducer(fn($x) => $x % 2 === 0);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([2, 4, 6, 8])
        ->execute();

    expect($result)->toBeTrue();
});

test('MatchesAllReducer returns false when at least one element does not match', function () {
    $reducer = new MatchesAllReducer(fn($x) => $x % 2 === 0);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([2, 3, 4])
        ->execute();

    expect($result)->toBeFalse();
});

test('MatchesAllReducer returns true for empty collection', function () {
    $reducer = new MatchesAllReducer(fn($x) => false);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([])
        ->execute();

    expect($result)->toBeTrue(); // Vacuous truth
});

test('MatchesAllReducer short-circuits on first non-match', function () {
    $count = 0;
    $reducer = new MatchesAllReducer(function ($x) use (&$count) {
        $count++;
        return $x < 5;
    });

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3, 10, 11, 12])
        ->execute();

    expect($result)->toBeFalse();
    expect($count)->toBe(4); // Stops at first non-match
});

test('MatchesAllReducer works with object predicates', function () {
    $data = [
        ['name' => 'Alice', 'active' => true],
        ['name' => 'Bob', 'active' => true],
        ['name' => 'Charlie', 'active' => true],
    ];

    $reducer = new MatchesAllReducer(fn($x) => $x['active']);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput($data)
        ->execute();

    expect($result)->toBeTrue();
});

test('MatchesAllReducer returns false when first element does not match', function () {
    $reducer = new MatchesAllReducer(fn($x) => $x > 5);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 6, 7, 8])
        ->execute();

    expect($result)->toBeFalse();
});

test('MatchesAllReducer with single element matching', function () {
    $reducer = new MatchesAllReducer(fn($x) => $x === 42);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([42])
        ->execute();

    expect($result)->toBeTrue();
});
