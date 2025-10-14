<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\Bool\MatchesAnyReducer;
use Cognesy\Stream\Transformation;

test('MatchesAnyReducer returns true when at least one element matches', function () {
    $reducer = new MatchesAnyReducer(fn($x) => $x > 3);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3, 4, 5])
        ->execute();

    expect($result)->toBeTrue();
});

test('MatchesAnyReducer returns false when no elements match', function () {
    $reducer = new MatchesAnyReducer(fn($x) => $x > 10);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3])
        ->execute();

    expect($result)->toBeFalse();
});

test('MatchesAnyReducer returns false for empty collection', function () {
    $reducer = new MatchesAnyReducer(fn($x) => true);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([])
        ->execute();

    expect($result)->toBeFalse();
});

test('MatchesAnyReducer short-circuits on first match', function () {
    $count = 0;
    $reducer = new MatchesAnyReducer(function ($x) use (&$count) {
        $count++;
        return $x === 3;
    });

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3, 4, 5])
        ->execute();

    expect($result)->toBeTrue();
    expect($count)->toBe(3); // Only processes until match
});

test('MatchesAnyReducer works with object predicates', function () {
    $data = [
        ['name' => 'Alice', 'age' => 25],
        ['name' => 'Bob', 'age' => 30],
        ['name' => 'Charlie', 'age' => 35],
    ];

    $reducer = new MatchesAnyReducer(fn($x) => $x['age'] > 32);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput($data)
        ->execute();

    expect($result)->toBeTrue();
});

test('MatchesAnyReducer returns true for first element match', function () {
    $reducer = new MatchesAnyReducer(fn($x) => $x === 1);

    $result = (new Transformation())
        ->withSink($reducer)
        ->withInput([1, 2, 3])
        ->execute();

    expect($result)->toBeTrue();
});
