<?php declare(strict_types=1);

use Cognesy\Stream\Sinks\FrequenciesReducer;
use Cognesy\Stream\Transducers\Map;
use Cognesy\Stream\Transformation;

test('FrequenciesReducer counts occurrences of each value', function () {
    $reducer = new FrequenciesReducer();
    $items = new ArrayIterator([1, 2, 1, 3, 2, 1, 4]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe([
        1 => 3,
        2 => 2,
        3 => 1,
        4 => 1,
    ]);
});

test('FrequenciesReducer with empty collection returns empty array', function () {
    $reducer = new FrequenciesReducer();
    $items = new ArrayIterator([]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe([]);
});

test('FrequenciesReducer with key function', function () {
    $reducer = new FrequenciesReducer(fn($x) => $x->category);
    $items = new ArrayIterator([
        (object)['name' => 'Apple', 'category' => 'fruit'],
        (object)['name' => 'Carrot', 'category' => 'vegetable'],
        (object)['name' => 'Banana', 'category' => 'fruit'],
        (object)['name' => 'Broccoli', 'category' => 'vegetable'],
        (object)['name' => 'Orange', 'category' => 'fruit'],
    ]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe([
        'fruit' => 3,
        'vegetable' => 2,
    ]);
});

test('FrequenciesReducer with string values', function () {
    $reducer = new FrequenciesReducer();
    $items = new ArrayIterator(['a', 'b', 'a', 'c', 'a', 'b']);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe([
        'a' => 3,
        'b' => 2,
        'c' => 1,
    ]);
});

test('FrequenciesReducer works with transducers', function () {
    $reducer = new FrequenciesReducer();
    $items = new ArrayIterator([1, 2, 3, 4, 5]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline
        ->through(new Map(fn($x) => $x % 2 === 0 ? 'even' : 'odd'))
        ->executeOn($items);

    expect($result)->toBe([
        'odd' => 3,   // 1, 3, 5
        'even' => 2,  // 2, 4
    ]);
});

test('FrequenciesReducer counts all same values', function () {
    $reducer = new FrequenciesReducer();
    $items = new ArrayIterator([7, 7, 7, 7]);
    $pipeline = new Transformation([], $reducer);

    $result = $pipeline->executeOn($items);

    expect($result)->toBe([7 => 4]);
});
