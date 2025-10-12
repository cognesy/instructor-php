<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\Sinks\ToArrayReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\DropUntil;
use Cognesy\Utils\Transducer\Transducers\Map;

test('DropUntil drops elements until predicate is true (inclusive)', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5, 6]);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => $x === 4))
        ->applyTo($items);

    expect($result)->toBe([4, 5, 6]); // Includes 4 and rest
});

test('DropUntil with predicate never matching drops all', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => $x > 10))
        ->applyTo($items);

    expect($result)->toBe([]);
});

test('DropUntil with predicate matching first element', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => $x === 1))
        ->applyTo($items);

    expect($result)->toBe([1, 2, 3, 4, 5]); // Takes all starting from 1
});

test('DropUntil with empty collection', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([]);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => true))
        ->applyTo($items);

    expect($result)->toBe([]);
});

test('DropUntil processes all elements after match', function () {
    $processed = [];
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => $x === 4))
        ->through(new Map(function($x) use (&$processed) {
            $processed[] = $x;
            return $x;
        }))
        ->applyTo($items);

    expect($result)->toBe([4, 5, 6, 7, 8, 9]);
    expect($processed)->toBe([4, 5, 6, 7, 8, 9]);
});

test('DropUntil with string delimiter', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator(['skip', 'skip', 'START', 'keep', 'keep']);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => $x === 'START'))
        ->applyTo($items);

    expect($result)->toBe(['START', 'keep', 'keep']);
});

test('DropUntil with condition on value range', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    $items = new ArrayIterator([1, 2, 3, 10, 11, 12, 3, 4]);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => $x >= 10))
        ->applyTo($items);

    expect($result)->toBe([10, 11, 12, 3, 4]); // Starts from first match
});

test('DropUntil is inclusive vs DropWhile which is exclusive', function () {
    $reducer = new ToArrayReducer();
    $pipeline = new Transduce([], $reducer);

    // DropUntil includes the matching element
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $result = $pipeline
        ->through(new DropUntil(fn($x) => $x >= 3))
        ->applyTo($items);

    expect($result)->toBe([3, 4, 5]); // Includes 3
});
