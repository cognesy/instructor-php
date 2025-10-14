<?php declare(strict_types=1);

use Cognesy\Stream\Iterator\IteratorUtils;
use Cognesy\Stream\Support\TeeState;

test('retrieves values sequentially for single branch', function () {
    $source = IteratorUtils::toIterator([1, 2, 3]);
    $state = new TeeState($source, 1);

    expect($state->hasValue(0))->toBeTrue();
    expect($state->nextValue(0))->toBe(1);
    expect($state->hasValue(0))->toBeTrue();
    expect($state->nextValue(0))->toBe(2);
    expect($state->hasValue(0))->toBeTrue();
    expect($state->nextValue(0))->toBe(3);
    expect($state->hasValue(0))->toBeFalse();
});

test('retrieves same values for multiple branches', function () {
    $source = IteratorUtils::toIterator([1, 2, 3]);
    $state = new TeeState($source, 2);

    expect($state->nextValue(0))->toBe(1);
    expect($state->nextValue(1))->toBe(1);
    expect($state->nextValue(0))->toBe(2);
    expect($state->nextValue(1))->toBe(2);
});

test('buffers values for slower branches', function () {
    $source = IteratorUtils::toIterator([1, 2, 3, 4]);
    $state = new TeeState($source, 2);

    // Fast branch reads ahead
    expect($state->nextValue(0))->toBe(1);
    expect($state->nextValue(0))->toBe(2);
    expect($state->nextValue(0))->toBe(3);

    // Slow branch reads from buffer
    expect($state->nextValue(1))->toBe(1);
    expect($state->nextValue(1))->toBe(2);
    expect($state->nextValue(1))->toBe(3);
});

test('hasValue returns false when branch is deactivated', function () {
    $source = IteratorUtils::toIterator([1, 2, 3]);
    $state = new TeeState($source, 1);

    expect($state->nextValue(0))->toBe(1);
    $state->deactivate(0);
    expect($state->hasValue(0))->toBeFalse();
});

test('handles empty source', function () {
    $source = IteratorUtils::toIterator([]);
    $state = new TeeState($source, 2);

    expect($state->hasValue(0))->toBeFalse();
    expect($state->hasValue(1))->toBeFalse();
});

test('initializes all branches as active', function () {
    $source = IteratorUtils::toIterator([1, 2, 3]);
    $state = new TeeState($source, 3);

    expect($state->nextValue(0))->toBe(1);
    expect($state->nextValue(1))->toBe(1);
    expect($state->nextValue(2))->toBe(1);
});

test('cleans up buffer after all branches advance', function () {
    $source = IteratorUtils::toIterator([1, 2, 3, 4, 5]);
    $state = new TeeState($source, 2);

    // Fast branch reads ahead
    $state->nextValue(0);
    $state->nextValue(0);
    $state->nextValue(0);

    // Slow branch catches up, buffer should cleanup
    $state->nextValue(1);
    $state->nextValue(1);
    $state->nextValue(1);

    // Both should continue reading
    expect($state->nextValue(0))->toBe(4);
    expect($state->nextValue(1))->toBe(4);
});

test('handles deactivation of single branch', function () {
    $source = IteratorUtils::toIterator([1, 2, 3, 4]);
    $state = new TeeState($source, 2);

    $state->nextValue(0);
    $state->nextValue(0);
    $state->deactivate(0);

    // Remaining branch should continue normally
    expect($state->nextValue(1))->toBe(1);
    expect($state->nextValue(1))->toBe(2);
    expect($state->nextValue(1))->toBe(3);
    expect($state->nextValue(1))->toBe(4);
});

test('handles deactivation of all branches', function () {
    $source = IteratorUtils::toIterator([1, 2, 3]);
    $state = new TeeState($source, 2);

    $state->nextValue(0);
    $state->deactivate(0);
    $state->deactivate(1);

    expect($state->hasValue(0))->toBeFalse();
    expect($state->hasValue(1))->toBeFalse();
});

test('preserves special values including null', function () {
    $source = IteratorUtils::toIterator([null, false, 0, '', []]);
    $state = new TeeState($source, 1);

    expect($state->nextValue(0))->toBeNull();
    expect($state->nextValue(0))->toBe(false);
    expect($state->nextValue(0))->toBe(0);
    expect($state->nextValue(0))->toBe('');
    expect($state->nextValue(0))->toBe([]);
    expect($state->hasValue(0))->toBeFalse();
});

test('handles branches reading at vastly different speeds', function () {
    $source = IteratorUtils::toIterator(range(1, 100));
    $state = new TeeState($source, 2);

    // Fast branch reads everything
    for ($i = 1; $i <= 100; $i++) {
        expect($state->nextValue(0))->toBe($i);
    }
    expect($state->hasValue(0))->toBeFalse();

    // Slow branch should still get everything from buffer
    for ($i = 1; $i <= 100; $i++) {
        expect($state->nextValue(1))->toBe($i);
    }
    expect($state->hasValue(1))->toBeFalse();
});

test('multiple branches can read from buffer simultaneously', function () {
    $source = IteratorUtils::toIterator([1, 2, 3, 4]);
    $state = new TeeState($source, 3);

    // All branches read first value
    expect($state->nextValue(0))->toBe(1);
    expect($state->nextValue(1))->toBe(1);
    expect($state->nextValue(2))->toBe(1);

    // One branch reads ahead
    expect($state->nextValue(0))->toBe(2);
    expect($state->nextValue(0))->toBe(3);

    // Others catch up from buffer
    expect($state->nextValue(1))->toBe(2);
    expect($state->nextValue(2))->toBe(2);
});

test('buffer cleanup happens progressively', function () {
    $source = IteratorUtils::toIterator([1, 2, 3, 4, 5]);
    $state = new TeeState($source, 2);

    // Create buffer gap
    $state->nextValue(0); // 1
    $state->nextValue(0); // 2
    $state->nextValue(0); // 3

    // Slow branch starts catching up
    $state->nextValue(1); // 1 - cleanup should happen
    $state->nextValue(1); // 2 - cleanup should happen

    // Both continue
    expect($state->nextValue(0))->toBe(4);
    expect($state->nextValue(1))->toBe(3);
});

test('deactivating fastest branch allows buffer cleanup', function () {
    $source = IteratorUtils::toIterator([1, 2, 3, 4, 5]);
    $state = new TeeState($source, 2);

    // Fast branch reads ahead
    $state->nextValue(0); // 1
    $state->nextValue(0); // 2
    $state->nextValue(0); // 3

    // Deactivate fast branch
    $state->deactivate(0);

    // Slow branch should continue from beginning
    expect($state->nextValue(1))->toBe(1);
    expect($state->nextValue(1))->toBe(2);
});

test('source is consumed on demand', function () {
    $consumed = [];
    $generator = function () use (&$consumed) {
        for ($i = 1; $i <= 5; $i++) {
            $consumed[] = $i;
            yield $i;
        }
    };

    $source = IteratorUtils::toIterator($generator());
    $state = new TeeState($source, 2);

    expect($consumed)->toBe([]);

    // First read initializes source (reads value 1, advances to position 2)
    // Note: Generator execution reaches the next yield, so value 2 is "prepared"
    $state->nextValue(0);
    expect($consumed)->toBe([1, 2]);

    // Second read from fast branch consumes next value
    $state->nextValue(0);
    expect($consumed)->toBe([1, 2, 3]);

    // Slow branch reads from buffer (no new consumption)
    $state->nextValue(1);
    expect($consumed)->toBe([1, 2, 3]);

    $state->nextValue(1);
    expect($consumed)->toBe([1, 2, 3]);
});
