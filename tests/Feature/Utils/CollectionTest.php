<?php

use Cognesy\Instructor\Utils\Collection;

test('can create a new collection', function () {
    $collection = new Collection(stdClass::class);

    $this->assertInstanceOf(Collection::class, $collection);
});

test('can create a new collection with items', function () {
    $item1 = new stdClass();
    $item2 = new stdClass();
    $collection = new Collection(stdClass::class, [$item1, $item2]);

    $this->assertCount(2, $collection);
    $this->assertSame($item1, $collection[0]);
    $this->assertSame($item2, $collection[1]);
});

test('can create a new collection using static method', function () {
    $collection = Collection::of(stdClass::class);

    $this->assertInstanceOf(Collection::class, $collection);
});

test('can add items to collection', function () {
    $item1 = new stdClass();
    $item2 = new stdClass();
    $collection = Collection::of(stdClass::class)->add([$item1, $item2]);

    $this->assertCount(2, $collection);
    $this->assertSame($item1, $collection[0]);
    $this->assertSame($item2, $collection[1]);
});

test('throws exception when adding invalid item type', function () {
    $collection = Collection::of(stdClass::class);

    $this->expectException(InvalidArgumentException::class);
    $collection->add([new stdClass(), new DateTime()]);
});

test('can access items using array access', function () {
    $item1 = new stdClass();
    $item2 = new stdClass();
    $collection = new Collection(stdClass::class, [$item1, $item2]);

    $this->assertTrue(isset($collection[0]));
    $this->assertSame($item1, $collection[0]);
    $this->assertFalse(isset($collection[2]));
});

test('can modify items using array access', function () {
    $item1 = new stdClass();
    $item2 = new stdClass();
    $collection = new Collection(stdClass::class, [$item1]);

    $collection[0] = $item2;

    $this->assertSame($item2, $collection[0]);
});

test('can iterate over collection', function () {
    $item1 = new stdClass();
    $item2 = new stdClass();
    $collection = new Collection(stdClass::class, [$item1, $item2]);

    $items = [];
    foreach ($collection as $item) {
        $items[] = $item;
    }

    $this->assertSame([$item1, $item2], $items);
});