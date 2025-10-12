<?php declare(strict_types=1);

use Cognesy\Stream\Support\Tee;

// EMPTY/MINIMAL SOURCES

test('empty array produces empty branches', function () {
    $source = [];
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe([]);
    expect(iterator_to_array($b2))->toBe([]);
});

test('single element produces single element in all branches', function () {
    $source = [42];
    [$b1, $b2, $b3] = Tee::split($source, 3);

    expect(iterator_to_array($b1))->toBe([42]);
    expect(iterator_to_array($b2))->toBe([42]);
    expect(iterator_to_array($b3))->toBe([42]);
});

test('two elements with two branches', function () {
    $source = ['a', 'b'];
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe(['a', 'b']);
    expect(iterator_to_array($b2))->toBe(['a', 'b']);
});

// SPECIAL VALUES

test('preserves null values in stream', function () {
    $source = [null, 1, null, 2, null];
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe([null, 1, null, 2, null]);
    expect(iterator_to_array($b2))->toBe([null, 1, null, 2, null]);
});

test('preserves false, 0, empty string in stream', function () {
    $source = [false, 0, '', 'text'];
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe([false, 0, '', 'text']);
    expect(iterator_to_array($b2))->toBe([false, 0, '', 'text']);
});

test('handles mixed truthy and falsy values', function () {
    $source = [true, false, 1, 0, 'yes', '', null, [], [1]];
    [$b1, $b2] = Tee::split($source);

    $result = [true, false, 1, 0, 'yes', '', null, [], [1]];
    expect(iterator_to_array($b1))->toBe($result);
    expect(iterator_to_array($b2))->toBe($result);
});

test('preserves objects in stream', function () {
    $obj1 = (object)['name' => 'Alice'];
    $obj2 = (object)['name' => 'Bob'];
    $source = [$obj1, $obj2];

    [$b1, $b2] = Tee::split($source);

    $r1 = iterator_to_array($b1);
    $r2 = iterator_to_array($b2);

    expect($r1)->toHaveCount(2);
    expect($r2)->toHaveCount(2);
    expect($r1[0])->toBe($obj1);
    expect($r2[0])->toBe($obj1);
    expect($r1[1])->toBe($obj2);
    expect($r2[1])->toBe($obj2);
});

test('preserves nested arrays and complex structures', function () {
    $source = [
        ['a' => 1, 'b' => [2, 3]],
        ['c' => ['d' => ['e' => 4]]],
    ];

    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe($source);
    expect(iterator_to_array($b2))->toBe($source);
});

// BRANCH MANAGEMENT

test('abandoning branch early does not block others', function () {
    $source = range(1, 10);
    [$b1, $b2] = Tee::split($source);

    // B1 reads 2 items then is abandoned
    $partial = [];
    foreach ($b1 as $val) {
        $partial[] = $val;
        if ($val === 2) {
            break;
        }
    }
    unset($b1);

    // B2 should get everything
    expect(iterator_to_array($b2))->toBe(range(1, 10));
});

test('abandoning fastest branch allows buffer cleanup', function () {
    $source = range(1, 100);
    [$fast, $slow] = Tee::split($source);

    // Fast branch reads 50 items
    for ($i = 0; $i < 50; $i++) {
        $fast->current();
        $fast->next();
    }

    // Abandon fast branch
    unset($fast);

    // Slow branch should still work
    $result = iterator_to_array($slow);
    expect($result)->toHaveCount(100);
    expect($result[0])->toBe(1);
});

test('abandoning slowest branch while fast continues', function () {
    $source = range(1, 20);
    [$fast, $slow] = Tee::split($source);

    // Fast reads 10
    for ($i = 0; $i < 10; $i++) {
        $fast->current();
        $fast->next();
    }

    // Slow reads 2
    $slow->current();
    $slow->next();
    $slow->current();
    unset($slow);

    // Fast continues (use while loop after manual iteration)
    $remaining = [];
    while ($fast->valid()) {
        $remaining[] = $fast->current();
        $fast->next();
    }
    expect($remaining)->toHaveCount(10);
});

test('abandoning all branches except one', function () {
    $source = [1, 2, 3, 4];
    [$b1, $b2, $b3, $b4] = Tee::split($source, 4);

    // Read one value from each
    $b1->current();
    $b2->current();
    $b3->current();
    $b4->current();

    // Abandon b1, b2, b3
    unset($b1, $b2, $b3);

    // B4 should complete
    expect(iterator_to_array($b4))->toBe([1, 2, 3, 4]);
});

test('abandoning branch after partial consumption', function () {
    $source = [10, 20, 30, 40, 50];
    [$b1, $b2] = Tee::split($source);

    // B1 consumes some
    expect($b1->current())->toBe(10);
    $b1->next();
    expect($b1->current())->toBe(20);
    $b1->next();

    // Abandon B1
    unset($b1);

    // B2 gets everything
    expect(iterator_to_array($b2))->toBe([10, 20, 30, 40, 50]);
});

// SOURCE TYPES

test('works with array source', function () {
    $source = [1, 2, 3];
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe([1, 2, 3]);
    expect(iterator_to_array($b2))->toBe([1, 2, 3]);
});

test('works with ArrayIterator source', function () {
    $source = new ArrayIterator(['a', 'b', 'c']);
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe(['a', 'b', 'c']);
    expect(iterator_to_array($b2))->toBe(['a', 'b', 'c']);
});

test('works with generator source', function () {
    $gen = function () {
        for ($i = 1; $i <= 5; $i++) {
            yield $i;
        }
    };

    [$b1, $b2] = Tee::split($gen());

    expect(iterator_to_array($b1))->toBe([1, 2, 3, 4, 5]);
    expect(iterator_to_array($b2))->toBe([1, 2, 3, 4, 5]);
});

test('works with infinite generator limited consumption', function () {
    $infinite = function () {
        $i = 1;
        while (true) {
            yield $i++;
        }
    };

    [$b1, $b2] = Tee::split($infinite());

    // Take first 5 from each
    $r1 = [];
    foreach ($b1 as $val) {
        $r1[] = $val;
        if (count($r1) === 5) {
            break;
        }
    }

    $r2 = [];
    foreach ($b2 as $val) {
        $r2[] = $val;
        if (count($r2) === 5) {
            break;
        }
    }

    expect($r1)->toBe([1, 2, 3, 4, 5]);
    expect($r2)->toBe([1, 2, 3, 4, 5]);
});

test('works with custom Iterator implementation', function () {
    $iterator = new class implements Iterator {
        private array $data = ['x', 'y', 'z'];
        private int $position = 0;

        public function current(): mixed {
            return $this->data[$this->position];
        }

        public function next(): void {
            $this->position++;
        }

        public function key(): mixed {
            return $this->position;
        }

        public function valid(): bool {
            return isset($this->data[$this->position]);
        }

        public function rewind(): void {
            $this->position = 0;
        }
    };

    [$b1, $b2] = Tee::split($iterator);

    expect(iterator_to_array($b1))->toBe(['x', 'y', 'z']);
    expect(iterator_to_array($b2))->toBe(['x', 'y', 'z']);
});

test('works with IteratorAggregate', function () {
    $aggregate = new class implements IteratorAggregate {
        public function getIterator(): Traversable {
            return new ArrayIterator([100, 200, 300]);
        }
    };

    [$b1, $b2] = Tee::split($aggregate);

    expect(iterator_to_array($b1))->toBe([100, 200, 300]);
    expect(iterator_to_array($b2))->toBe([100, 200, 300]);
});

// INVALID INPUT

test('throws exception for zero branches', function () {
    Tee::split([1, 2, 3], 0);
})->throws(InvalidArgumentException::class, 'branches must be >= 1');

test('throws exception for negative branches', function () {
    Tee::split([1, 2, 3], -5);
})->throws(InvalidArgumentException::class, 'branches must be >= 1');
