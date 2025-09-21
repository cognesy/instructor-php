<?php declare(strict_types=1);

use Cognesy\Utils\Collection\ArraySet;

test('creates empty set with hash function', function () {
    $set = ArraySet::empty(fn($x) => (string)$x);

    expect($set->count())->toBe(0)
        ->and($set->values())->toBe([]);
});

test('creates set from values with deduplication', function () {
    $set = ArraySet::fromValues(
        fn($x) => (string)$x,
        [1, 2, 3, 2, 1]
    );

    expect($set->count())->toBe(3)
        ->and($set->values())->toBe([1, 2, 3]);
});

test('checks if item is contained', function () {
    $set = ArraySet::fromValues(
        fn($x) => (string)$x,
        ['apple', 'banana', 'orange']
    );

    expect($set->contains('apple'))->toBeTrue()
        ->and($set->contains('banana'))->toBeTrue()
        ->and($set->contains('grape'))->toBeFalse();
});

test('adds items immutably', function () {
    $original = ArraySet::fromValues(fn($x) => (string)$x, [1, 2]);
    $new = $original->withAdded(3, 4);

    expect($original->count())->toBe(2)
        ->and($original->contains(3))->toBeFalse()
        ->and($new->count())->toBe(4)
        ->and($new->contains(3))->toBeTrue()
        ->and($new->contains(4))->toBeTrue();
});

test('adding existing item is idempotent', function () {
    $set = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3]);
    $new = $set->withAdded(2);

    expect($new->count())->toBe(3)
        ->and($new->values())->toBe([1, 2, 3]);
});

test('removes items immutably', function () {
    $original = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3]);
    $new = $original->withRemoved(2);

    expect($original->contains(2))->toBeTrue()
        ->and($new->contains(2))->toBeFalse()
        ->and($new->count())->toBe(2)
        ->and($new->values())->toBe([1, 3]);
});

test('removing non-existent item is idempotent', function () {
    $set = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3]);
    $new = $set->withRemoved(4);

    expect($new->count())->toBe(3)
        ->and($new->values())->toBe([1, 2, 3]);
});

test('union combines sets', function () {
    $set1 = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3]);
    $set2 = ArraySet::fromValues(fn($x) => (string)$x, [3, 4, 5]);
    $union = $set1->union($set2);

    expect($union->count())->toBe(5)
        ->and($union->values())->toBe([1, 2, 3, 4, 5]);
});

test('intersect finds common elements', function () {
    $set1 = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3, 4]);
    $set2 = ArraySet::fromValues(fn($x) => (string)$x, [3, 4, 5, 6]);
    $intersection = $set1->intersect($set2);

    expect($intersection->count())->toBe(2)
        ->and($intersection->values())->toBe([3, 4]);
});

test('diff finds elements in first but not second', function () {
    $set1 = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3, 4]);
    $set2 = ArraySet::fromValues(fn($x) => (string)$x, [3, 4, 5]);
    $diff = $set1->diff($set2);

    expect($diff->count())->toBe(2)
        ->and($diff->values())->toBe([1, 2]);
});

test('custom hash function works with objects', function () {
    $user1 = (object)['id' => 1, 'name' => 'Alice'];
    $user2 = (object)['id' => 2, 'name' => 'Bob'];
    $user3 = (object)['id' => 1, 'name' => 'Alice Updated']; // same id as user1

    $set = ArraySet::fromValues(
        fn($u) => (string)$u->id,
        [$user1, $user2, $user3]
    );

    expect($set->count())->toBe(2) // user3 replaces user1 due to same hash
        ->and($set->values()[0]->name)->toBe('Alice Updated')
        ->and($set->values()[1]->name)->toBe('Bob');
});

test('custom equals function provides additional safety', function () {
    $item1 = ['id' => 1, 'value' => 'a'];
    $item2 = ['id' => 2, 'value' => 'b'];
    $item3 = ['id' => 1, 'value' => 'c']; // same id, different value

    $set = ArraySet::fromValues(
        fn($i) => (string)$i['id'],
        [$item1, $item2],
        fn($a, $b) => $a['id'] === $b['id'] && $a['value'] === $b['value']
    );

    // Contains should use the equals function
    expect($set->contains($item1))->toBeTrue()
        ->and($set->contains($item3))->toBeFalse(); // false because value differs
});

test('iterates over values', function () {
    $values = [1, 2, 3];
    $set = ArraySet::fromValues(fn($x) => (string)$x, $values);

    $result = [];
    foreach ($set as $value) {
        $result[] = $value;
    }

    expect($result)->toBe($values);
});

test('handles empty intersect', function () {
    $set1 = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3]);
    $set2 = ArraySet::fromValues(fn($x) => (string)$x, [4, 5, 6]);
    $intersection = $set1->intersect($set2);

    expect($intersection->count())->toBe(0)
        ->and($intersection->values())->toBe([]);
});

test('handles empty diff', function () {
    $set1 = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3]);
    $set2 = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3, 4]);
    $diff = $set1->diff($set2);

    expect($diff->count())->toBe(0)
        ->and($diff->values())->toBe([]);
});

test('handles multiple adds and removes', function () {
    $set = ArraySet::fromValues(fn($x) => (string)$x, [1, 2, 3]);

    $modified = $set
        ->withAdded(4, 5, 6)
        ->withRemoved(1, 3, 5);

    expect($modified->count())->toBe(3)
        ->and($modified->values())->toBe([2, 4, 6]);
});

test('preserves insertion order with updates', function () {
    $set = ArraySet::fromValues(fn($x) => (string)$x['id'], [
        ['id' => 1, 'name' => 'first'],
        ['id' => 2, 'name' => 'second'],
        ['id' => 3, 'name' => 'third']
    ]);

    // Adding an item with same hash should replace but maintain position
    $updated = $set->withAdded(['id' => 2, 'name' => 'updated']);

    $values = $updated->values();
    expect($values[0]['id'])->toBe(1)
        ->and($values[1]['id'])->toBe(2)
        ->and($values[1]['name'])->toBe('updated')
        ->and($values[2]['id'])->toBe(3);
});

test('handles null values with appropriate hash', function () {
    $set = ArraySet::fromValues(
        fn($x) => $x === null ? 'null' : (string)$x,
        [null, 1, 2, null, 3]
    );

    expect($set->count())->toBe(4) // null is deduplicated
        ->and($set->contains(null))->toBeTrue()
        ->and($set->contains(1))->toBeTrue();
});

test('complex object deduplication', function () {
    class TestItem {
        public function __construct(
            public int $id,
            public string $category,
            public mixed $data
        ) {}
    }

    $item1 = new TestItem(1, 'A', ['x' => 1]);
    $item2 = new TestItem(2, 'B', ['y' => 2]);
    $item3 = new TestItem(1, 'A', ['z' => 3]); // same id and category as item1

    $set = ArraySet::fromValues(
        fn($i) => $i->id . '-' . $i->category,
        [$item1, $item2, $item3]
    );

    expect($set->count())->toBe(2)
        ->and($set->values()[0]->data)->toBe(['z' => 3]) // item3 replaced item1
        ->and($set->values()[1]->data)->toBe(['y' => 2]);
});