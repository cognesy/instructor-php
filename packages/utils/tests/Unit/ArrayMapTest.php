<?php declare(strict_types=1);

use Cognesy\Utils\Collection\ArrayMap;

test('creates empty map', function () {
    $map = ArrayMap::empty();

    expect($map->count())->toBe(0)
        ->and($map->toArray())->toBe([]);
});

test('creates map from array', function () {
    $data = ['key1' => 'value1', 'key2' => 'value2'];
    $map = ArrayMap::fromArray($data);

    expect($map->count())->toBe(2)
        ->and($map->toArray())->toBe($data);
});

test('checks if key exists', function () {
    $map = ArrayMap::fromArray(['foo' => 'bar', 'baz' => 'qux']);

    expect($map->has('foo'))->toBeTrue()
        ->and($map->has('baz'))->toBeTrue()
        ->and($map->has('nonexistent'))->toBeFalse();
});

test('gets value by key', function () {
    $map = ArrayMap::fromArray(['foo' => 'bar', 'nested' => ['a' => 1]]);

    expect($map->get('foo'))->toBe('bar')
        ->and($map->get('nested'))->toBe(['a' => 1]);
});

test('throws exception when getting non-existent key', function () {
    $map = ArrayMap::fromArray(['foo' => 'bar']);

    $map->get('nonexistent');
})->throws(OutOfBoundsException::class, 'ArrayMap key not found: nonexistent');

test('gets value or null for non-existent key', function () {
    $map = ArrayMap::fromArray(['foo' => 'bar']);

    expect($map->getOrNull('foo'))->toBe('bar')
        ->and($map->getOrNull('nonexistent'))->toBeNull();
});

test('adds new entry immutably', function () {
    $original = ArrayMap::fromArray(['foo' => 'bar']);
    $new = $original->with('baz', 'qux');

    expect($original->count())->toBe(1)
        ->and($original->has('baz'))->toBeFalse()
        ->and($new->count())->toBe(2)
        ->and($new->get('foo'))->toBe('bar')
        ->and($new->get('baz'))->toBe('qux');
});

test('overwrites existing entry immutably', function () {
    $original = ArrayMap::fromArray(['foo' => 'bar']);
    $new = $original->with('foo', 'updated');

    expect($original->get('foo'))->toBe('bar')
        ->and($new->get('foo'))->toBe('updated');
});

test('adds multiple entries preserving existing keys', function () {
    $original = ArrayMap::fromArray(['foo' => 'bar', 'baz' => 'qux']);
    $new = $original->withAll(['baz' => 'overwritten', 'new' => 'value']);

    expect($original->toArray())->toBe(['foo' => 'bar', 'baz' => 'qux'])
        ->and($new->toArray())->toBe(['foo' => 'bar', 'baz' => 'qux', 'new' => 'value']); // existing keys preserved
});

test('removes entry immutably', function () {
    $original = ArrayMap::fromArray(['foo' => 'bar', 'baz' => 'qux']);
    $new = $original->withRemoved('foo');

    expect($original->has('foo'))->toBeTrue()
        ->and($new->has('foo'))->toBeFalse()
        ->and($new->toArray())->toBe(['baz' => 'qux']);
});

test('removing non-existent key is idempotent', function () {
    $original = ArrayMap::fromArray(['foo' => 'bar']);
    $new = $original->withRemoved('nonexistent');

    expect($new)->toBe($original);
});

test('merges with another map with other winning', function () {
    $map1 = ArrayMap::fromArray(['foo' => 'bar', 'baz' => 'qux']);
    $map2 = ArrayMap::fromArray(['baz' => 'overwritten', 'new' => 'value']);
    $merged = $map1->merge($map2);

    expect($merged->toArray())->toBe([
        'foo' => 'bar',
        'baz' => 'overwritten', // map2 wins
        'new' => 'value'
    ]);
});

test('gets all keys', function () {
    $map = ArrayMap::fromArray(['foo' => 'bar', 'baz' => 'qux', 2 => 'numeric']);

    expect($map->keys())->toBe(['foo', 'baz', 2]);
});

test('gets all values', function () {
    $map = ArrayMap::fromArray(['foo' => 'bar', 'baz' => 'qux']);

    expect($map->values())->toBe(['bar', 'qux']);
});

test('iterates over entries', function () {
    $data = ['foo' => 'bar', 'baz' => 'qux'];
    $map = ArrayMap::fromArray($data);

    $result = [];
    foreach ($map as $key => $value) {
        $result[$key] = $value;
    }

    expect($result)->toBe($data);
});

test('handles numeric keys', function () {
    $map = ArrayMap::fromArray([1 => 'one', 2 => 'two', 'three' => 3]);

    expect($map->has(1))->toBeTrue()
        ->and($map->has(2))->toBeTrue()
        ->and($map->has('three'))->toBeTrue()
        ->and($map->get(1))->toBe('one')
        ->and($map->get('three'))->toBe(3);
});

test('handles null values', function () {
    $map = ArrayMap::fromArray(['nullable' => null, 'valid' => 'value']);

    expect($map->has('nullable'))->toBeTrue()
        ->and($map->get('nullable'))->toBeNull()
        ->and($map->getOrNull('nullable'))->toBeNull();
});

test('handles empty string keys', function () {
    $map = ArrayMap::fromArray(['' => 'empty-key-value', 'normal' => 'value']);

    expect($map->has(''))->toBeTrue()
        ->and($map->get(''))->toBe('empty-key-value');
});

test('preserves value types', function () {
    $object = new stdClass();
    $object->property = 'value';

    $map = ArrayMap::fromArray([
        'string' => 'text',
        'int' => 42,
        'float' => 3.14,
        'bool' => true,
        'array' => [1, 2, 3],
        'object' => $object,
        'null' => null
    ]);

    expect($map->get('string'))->toBeString()
        ->and($map->get('int'))->toBeInt()
        ->and($map->get('float'))->toBeFloat()
        ->and($map->get('bool'))->toBeBool()
        ->and($map->get('array'))->toBeArray()
        ->and($map->get('object'))->toBeObject()
        ->and($map->get('null'))->toBeNull();
});