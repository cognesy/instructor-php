<?php declare(strict_types=1);

use Cognesy\Utils\Collection\ArrayList;

test('creates empty list', function () {
    $list = ArrayList::empty();

    expect($list->count())->toBe(0)
        ->and($list->isEmpty())->toBeTrue()
        ->and($list->toArray())->toBe([]);
});

test('creates list from array', function () {
    $list = ArrayList::fromArray([1, 2, 3]);

    expect($list->count())->toBe(3)
        ->and($list->toArray())->toBe([1, 2, 3]);
});

test('creates list with variadic arguments', function () {
    $list = ArrayList::of(1, 2, 3);

    expect($list->count())->toBe(3)
        ->and($list->toArray())->toBe([1, 2, 3]);
});

test('reindexes array on creation', function () {
    $list = ArrayList::fromArray([
        2 => 'a',
        5 => 'b',
        1 => 'c'
    ]);

    expect($list->toArray())->toBe(['a', 'b', 'c'])
        ->and($list->get(0))->toBe('a')
        ->and($list->get(1))->toBe('b')
        ->and($list->get(2))->toBe('c');
});

test('gets element by index', function () {
    $list = ArrayList::fromArray(['a', 'b', 'c']);

    expect($list->get(0))->toBe('a')
        ->and($list->get(1))->toBe('b')
        ->and($list->get(2))->toBe('c');
});

test('throws exception for out of bounds index', function () {
    $list = ArrayList::fromArray([1, 2, 3]);

    $list->get(3);
})->throws(OutOfBoundsException::class, 'ArrayList index out of range: 3');

test('gets element or null by index', function () {
    $list = ArrayList::fromArray(['a', 'b', 'c']);

    expect($list->getOrNull(0))->toBe('a')
        ->and($list->getOrNull(1))->toBe('b')
        ->and($list->getOrNull(2))->toBe('c')
        ->and($list->getOrNull(3))->toBeNull()
        ->and($list->getOrNull(-1))->toBeNull();
});

test('gets first element', function () {
    $list = ArrayList::fromArray(['first', 'second', 'third']);

    expect($list->first())->toBe('first');
});

test('returns null for first on empty list', function () {
    $list = ArrayList::empty();

    expect($list->first())->toBeNull();
});

test('gets last element', function () {
    $list = ArrayList::fromArray(['first', 'second', 'last']);

    expect($list->last())->toBe('last');
});

test('returns null for last on empty list', function () {
    $list = ArrayList::empty();

    expect($list->last())->toBeNull();
});

test('checks if list is empty', function () {
    $empty = ArrayList::empty();
    $notEmpty = ArrayList::of(1);

    expect($empty->isEmpty())->toBeTrue()
        ->and($notEmpty->isEmpty())->toBeFalse();
});

test('adds elements immutably', function () {
    $original = ArrayList::fromArray([1, 2]);
    $new = $original->withAdded(3, 4);

    expect($original->count())->toBe(2)
        ->and($original->toArray())->toBe([1, 2])
        ->and($new->count())->toBe(4)
        ->and($new->toArray())->toBe([1, 2, 3, 4]);
});

test('inserts elements at index immutably', function () {
    $original = ArrayList::fromArray(['a', 'b', 'c']);
    $new = $original->withInserted(1, 'x', 'y');

    expect($original->toArray())->toBe(['a', 'b', 'c'])
        ->and($new->toArray())->toBe(['a', 'x', 'y', 'b', 'c']);
});

test('inserts at beginning', function () {
    $list = ArrayList::fromArray([1, 2, 3]);
    $new = $list->withInserted(0, 'start');

    expect($new->toArray())->toBe(['start', 1, 2, 3]);
});

test('inserts at end', function () {
    $list = ArrayList::fromArray([1, 2, 3]);
    $new = $list->withInserted(3, 'end');

    expect($new->toArray())->toBe([1, 2, 3, 'end']);
});

test('throws exception for invalid insert index', function () {
    $list = ArrayList::fromArray([1, 2, 3]);

    expect(fn() => $list->withInserted(-1, 'x'))
        ->toThrow(OutOfBoundsException::class, 'Insert index out of range: -1');

    expect(fn() => $list->withInserted(4, 'x'))
        ->toThrow(OutOfBoundsException::class, 'Insert index out of range: 4');
});

test('removes elements at index immutably', function () {
    $original = ArrayList::fromArray(['a', 'b', 'c', 'd']);
    $new = $original->withRemovedAt(1, 2);

    expect($original->toArray())->toBe(['a', 'b', 'c', 'd'])
        ->and($new->toArray())->toBe(['a', 'd']);
});

test('removes single element by default', function () {
    $list = ArrayList::fromArray([1, 2, 3, 4]);
    $new = $list->withRemovedAt(2);

    expect($new->toArray())->toBe([1, 2, 4]);
});

test('throws exception for invalid remove index', function () {
    $list = ArrayList::fromArray([1, 2, 3]);

    expect(fn() => $list->withRemovedAt(-1))
        ->toThrow(OutOfBoundsException::class, 'Remove index out of range: -1');

    expect(fn() => $list->withRemovedAt(3))
        ->toThrow(OutOfBoundsException::class, 'Remove index out of range: 3');
});

test('throws exception for negative removal count', function () {
    $list = ArrayList::fromArray([1, 2, 3]);

    $list->withRemovedAt(0, -1);
})->throws(OutOfBoundsException::class, 'Removal count must be >= 0');

test('filters elements', function () {
    $list = ArrayList::fromArray([1, 2, 3, 4, 5, 6]);
    $filtered = $list->filter(fn($x) => $x % 2 === 0);

    expect($filtered->toArray())->toBe([2, 4, 6]);
});

test('filter preserves list semantics', function () {
    $list = ArrayList::fromArray(['a', 'b', 'c', 'd']);
    $filtered = $list->filter(fn($x) => $x !== 'b' && $x !== 'd');

    expect($filtered->get(0))->toBe('a')
        ->and($filtered->get(1))->toBe('c')
        ->and($filtered->count())->toBe(2);
});

test('maps elements', function () {
    $list = ArrayList::fromArray([1, 2, 3]);
    $mapped = $list->map(fn($x) => $x * 2);

    expect($mapped->toArray())->toBe([2, 4, 6]);
});

test('map preserves count', function () {
    $list = ArrayList::fromArray(['a', 'b', 'c']);
    $mapped = $list->map(fn($x) => strtoupper($x));

    expect($mapped->count())->toBe(3)
        ->and($mapped->toArray())->toBe(['A', 'B', 'C']);
});

test('reduces to single value', function () {
    $list = ArrayList::fromArray([1, 2, 3, 4]);
    $sum = $list->reduce(fn($acc, $x) => $acc + $x, 0);

    expect($sum)->toBe(10);
});

test('reduce with string concatenation', function () {
    $list = ArrayList::fromArray(['a', 'b', 'c']);
    $result = $list->reduce(fn($acc, $x) => $acc . $x, '');

    expect($result)->toBe('abc');
});

test('reduce on empty list returns initial value', function () {
    $list = ArrayList::empty();
    $result = $list->reduce(fn($acc, $x) => $acc + $x, 42);

    expect($result)->toBe(42);
});

test('concatenates lists', function () {
    $list1 = ArrayList::fromArray([1, 2, 3]);
    $list2 = ArrayList::fromArray([4, 5, 6]);
    $concatenated = $list1->concat($list2);

    expect($concatenated->toArray())->toBe([1, 2, 3, 4, 5, 6]);
});

test('concat with empty list', function () {
    $list = ArrayList::fromArray([1, 2, 3]);
    $empty = ArrayList::empty();

    expect($list->concat($empty)->toArray())->toBe([1, 2, 3])
        ->and($empty->concat($list)->toArray())->toBe([1, 2, 3]);
});

test('all returns array', function () {
    $list = ArrayList::fromArray([1, 2, 3]);

    expect($list->all())->toBe([1, 2, 3])
        ->and($list->all())->toBe($list->toArray());
});

test('iterates over elements', function () {
    $list = ArrayList::fromArray(['a', 'b', 'c']);

    $result = [];
    foreach ($list as $index => $value) {
        $result[$index] = $value;
    }

    expect($result)->toBe(['a', 'b', 'c']);
});

test('handles mixed types', function () {
    $object = new stdClass();
    $list = ArrayList::of(
        'string',
        42,
        3.14,
        true,
        null,
        [1, 2, 3],
        $object
    );

    expect($list->get(0))->toBeString()
        ->and($list->get(1))->toBeInt()
        ->and($list->get(2))->toBeFloat()
        ->and($list->get(3))->toBeBool()
        ->and($list->get(4))->toBeNull()
        ->and($list->get(5))->toBeArray()
        ->and($list->get(6))->toBeObject();
});

test('chaining operations', function () {
    $list = ArrayList::fromArray([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

    $result = $list
        ->filter(fn($x) => $x % 2 === 0)  // [2, 4, 6, 8, 10]
        ->map(fn($x) => $x * 2)            // [4, 8, 12, 16, 20]
        ->withAdded(24)                    // [4, 8, 12, 16, 20, 24]
        ->withRemovedAt(0)                 // [8, 12, 16, 20, 24]
        ->withInserted(2, 14);             // [8, 12, 14, 16, 20, 24]

    expect($result->toArray())->toBe([8, 12, 14, 16, 20, 24]);
});

test('complex reduce operations', function () {
    $list = ArrayList::fromArray([
        ['name' => 'Alice', 'score' => 85],
        ['name' => 'Bob', 'score' => 92],
        ['name' => 'Charlie', 'score' => 78]
    ]);

    $totalScore = $list->reduce(
        fn($acc, $student) => $acc + $student['score'],
        0
    );

    $highestScore = $list->reduce(
        fn($max, $student) => $student['score'] > $max ? $student['score'] : $max,
        0
    );

    expect($totalScore)->toBe(255)
        ->and($highestScore)->toBe(92);
});

test('remove beyond list bounds removes to end', function () {
    $list = ArrayList::fromArray([1, 2, 3, 4, 5]);
    $new = $list->withRemovedAt(2, 10); // Remove from index 2 to end

    expect($new->toArray())->toBe([1, 2]);
});

test('reverses list immutably', function () {
    $original = ArrayList::fromArray([1, 2, 3, 4, 5]);
    $reversed = $original->reverse();

    expect($original->toArray())->toBe([1, 2, 3, 4, 5])
        ->and($reversed->toArray())->toBe([5, 4, 3, 2, 1]);
});

test('reverse preserves empty list', function () {
    $empty = ArrayList::empty();
    $reversed = $empty->reverse();

    expect($reversed->toArray())->toBe([])
        ->and($reversed->isEmpty())->toBeTrue();
});

test('reverse handles single element', function () {
    $single = ArrayList::of('only');
    $reversed = $single->reverse();

    expect($reversed->toArray())->toBe(['only']);
});

test('reverse with mixed types', function () {
    $list = ArrayList::of(1, 'string', null, [1, 2], true);
    $reversed = $list->reverse();

    expect($reversed->toArray())->toBe([true, [1, 2], null, 'string', 1]);
});

test('getOrNull with empty list', function () {
    $empty = ArrayList::empty();

    expect($empty->getOrNull(0))->toBeNull()
        ->and($empty->getOrNull(1))->toBeNull();
});

test('reverse chaining with other operations', function () {
    $list = ArrayList::fromArray([1, 2, 3, 4, 5, 6]);

    $result = $list
        ->filter(fn($x) => $x % 2 === 0)  // [2, 4, 6]
        ->reverse()                       // [6, 4, 2]
        ->withAdded(8)                    // [6, 4, 2, 8]
        ->map(fn($x) => $x * 2);          // [12, 8, 4, 16]

    expect($result->toArray())->toBe([12, 8, 4, 16]);
});