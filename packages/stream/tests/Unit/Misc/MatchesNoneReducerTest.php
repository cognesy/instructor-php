<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\Bool\MatchesNoneReducer;
use Cognesy\Stream\Transformation;

test('MatchesNoneReducer returns true when no elements match', function () {
    $reducer = new MatchesNoneReducer(fn($x) => $x % 2 === 0);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 3, 5, 7])
        ->execute();

    expect($result)->toBeTrue();
});

test('MatchesNoneReducer returns false when at least one element matches', function () {
    $reducer = new MatchesNoneReducer(fn($x) => $x % 2 === 0);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3])
        ->execute();

    expect($result)->toBeFalse();
});

test('MatchesNoneReducer returns true for empty collection', function () {
    $reducer = new MatchesNoneReducer(fn($x) => true);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([])
        ->execute();

    expect($result)->toBeTrue(); // Vacuous truth
});

test('MatchesNoneReducer short-circuits on first match', function () {
    $count = 0;
    $reducer = new MatchesNoneReducer(function ($x) use (&$count) {
        $count++;
        return $x > 5;
    });

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3, 10, 11, 12])
        ->execute();

    expect($result)->toBeFalse();
    expect($count)->toBe(4); // Stops at first match
});

test('MatchesNoneReducer works with object predicates', function () {
    $data = [
        ['name' => 'Alice', 'age' => 25],
        ['name' => 'Bob', 'age' => 30],
        ['name' => 'Charlie', 'age' => 35],
    ];

    $reducer = new MatchesNoneReducer(fn($x) => $x['age'] > 40);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput($data)
        ->execute();

    expect($result)->toBeTrue();
});

test('MatchesNoneReducer returns false when first element matches', function () {
    $reducer = new MatchesNoneReducer(fn($x) => $x === 1);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3])
        ->execute();

    expect($result)->toBeFalse();
});

test('MatchesNoneReducer with single element not matching', function () {
    $reducer = new MatchesNoneReducer(fn($x) => $x > 100);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([42])
        ->execute();

    expect($result)->toBeTrue();
});

test('MatchesNoneReducer checks all elements when none match', function () {
    $count = 0;
    $reducer = new MatchesNoneReducer(function ($x) use (&$count) {
        $count++;
        return $x < 0;
    });

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3, 4, 5])
        ->execute();

    expect($result)->toBeTrue();
    expect($count)->toBe(5); // Checks all elements
});
