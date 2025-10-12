<?php declare(strict_types=1);

use Cognesy\Utils\Transducer\Sinks\GroupByReducer;
use Cognesy\Utils\Transducer\Transduce;
use Cognesy\Utils\Transducer\Transducers\Map;

test('GroupByReducer groups elements by key', function () {
    $reducer = new GroupByReducer(fn($x) => $x % 2 === 0 ? 'even' : 'odd');
    $items = new ArrayIterator([1, 2, 3, 4, 5, 6]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe([
        'odd' => [1, 3, 5],
        'even' => [2, 4, 6],
    ]);
});

test('GroupByReducer with empty collection returns empty array', function () {
    $reducer = new GroupByReducer(fn($x) => $x);
    $items = new ArrayIterator([]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toBe([]);
});

test('GroupByReducer groups objects by property', function () {
    $reducer = new GroupByReducer(fn($x) => $x->category);
    $items = new ArrayIterator([
        (object)['name' => 'Apple', 'category' => 'fruit'],
        (object)['name' => 'Carrot', 'category' => 'vegetable'],
        (object)['name' => 'Banana', 'category' => 'fruit'],
        (object)['name' => 'Broccoli', 'category' => 'vegetable'],
    ]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result)->toHaveKey('fruit');
    expect($result)->toHaveKey('vegetable');
    expect(count($result['fruit']))->toBe(2);
    expect(count($result['vegetable']))->toBe(2);
});

test('GroupByReducer handles non-consecutive same keys', function () {
    $reducer = new GroupByReducer(fn($x) => $x->type);
    $items = new ArrayIterator([
        (object)['id' => 1, 'type' => 'a'],
        (object)['id' => 2, 'type' => 'b'],
        (object)['id' => 3, 'type' => 'a'],
        (object)['id' => 4, 'type' => 'b'],
    ]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline->applyTo($items);

    expect($result['a'])->toHaveCount(2);
    expect($result['b'])->toHaveCount(2);
    expect($result['a'][0]->id)->toBe(1);
    expect($result['a'][1]->id)->toBe(3);
});

test('GroupByReducer works with transducers', function () {
    $reducer = new GroupByReducer(fn($x) => $x > 5 ? 'high' : 'low');
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transduce([], $reducer);

    $result = $pipeline
        ->through(new Map(fn($x) => $x * 2))
        ->applyTo($items);

    expect($result)->toBe([
        'low' => [2, 4],
        'high' => [6, 8, 10],
    ]);
});
