<?php declare(strict_types=1);

use Cognesy\Stream\Support\Tee;

// BASIC SPLITTING

test('splits array into 2 branches and consumes both fully', function () {
    $source = [1, 2, 3, 4, 5];
    [$branch1, $branch2] = Tee::split($source);

    $result1 = iterator_to_array($branch1);
    $result2 = iterator_to_array($branch2);

    expect($result1)->toBe([1, 2, 3, 4, 5]);
    expect($result2)->toBe([1, 2, 3, 4, 5]);
});

test('splits array into 3+ branches and consumes all fully', function () {
    $source = [10, 20, 30];
    [$b1, $b2, $b3, $b4] = Tee::split($source, 4);

    expect(iterator_to_array($b1))->toBe([10, 20, 30]);
    expect(iterator_to_array($b2))->toBe([10, 20, 30]);
    expect(iterator_to_array($b3))->toBe([10, 20, 30]);
    expect(iterator_to_array($b4))->toBe([10, 20, 30]);
});

test('splits array into single branch', function () {
    $source = [1, 2, 3];
    [$branch] = Tee::split($source, 1);

    expect(iterator_to_array($branch))->toBe([1, 2, 3]);
});

test('splits generator into multiple branches', function () {
    $generator = function () {
        yield 'a';
        yield 'b';
        yield 'c';
    };

    [$b1, $b2] = Tee::split($generator());

    expect(iterator_to_array($b1))->toBe(['a', 'b', 'c']);
    expect(iterator_to_array($b2))->toBe(['a', 'b', 'c']);
});

test('splits ArrayIterator into multiple branches', function () {
    $source = new ArrayIterator([1, 2, 3]);
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe([1, 2, 3]);
    expect(iterator_to_array($b2))->toBe([1, 2, 3]);
});

// SEQUENTIAL CONSUMPTION

test('all branches consume in lockstep', function () {
    $source = [1, 2, 3, 4];
    [$b1, $b2] = Tee::split($source);

    expect($b1->current())->toBe(1);
    expect($b2->current())->toBe(1);

    $b1->next();
    $b2->next();

    expect($b1->current())->toBe(2);
    expect($b2->current())->toBe(2);

    $b1->next();
    $b2->next();

    expect($b1->current())->toBe(3);
    expect($b2->current())->toBe(3);
});

test('one branch reads ahead, others catch up later', function () {
    $source = [1, 2, 3, 4, 5];
    [$fast, $slow] = Tee::split($source);

    // Fast branch reads all
    $fastResults = iterator_to_array($fast);
    expect($fastResults)->toBe([1, 2, 3, 4, 5]);

    // Slow branch reads from buffer
    $slowResults = iterator_to_array($slow);
    expect($slowResults)->toBe([1, 2, 3, 4, 5]);
});

test('multiple branches at different speeds', function () {
    $source = range(1, 10);
    [$fast, $medium, $slow] = Tee::split($source, 3);

    // Fast reads 5
    for ($i = 0; $i < 5; $i++) {
        $fast->current();
        $fast->next();
    }

    // Medium reads 3
    for ($i = 0; $i < 3; $i++) {
        $medium->current();
        $medium->next();
    }

    // Slow reads 1
    $slow->current();
    $slow->next();

    // All continue to completion (continue with while loop, not foreach)
    $fastRemaining = [];
    while ($fast->valid()) {
        $fastRemaining[] = $fast->current();
        $fast->next();
    }
    expect($fastRemaining)->toBe([6, 7, 8, 9, 10]);

    $mediumRemaining = [];
    while ($medium->valid()) {
        $mediumRemaining[] = $medium->current();
        $medium->next();
    }
    expect($mediumRemaining)->toBe([4, 5, 6, 7, 8, 9, 10]);

    $slowRemaining = [];
    while ($slow->valid()) {
        $slowRemaining[] = $slow->current();
        $slow->next();
    }
    expect($slowRemaining)->toBe([2, 3, 4, 5, 6, 7, 8, 9, 10]);
});

test('branch consumes partially then another catches up', function () {
    $source = [1, 2, 3, 4, 5];
    [$b1, $b2] = Tee::split($source);

    // B1 reads 3 items and advances past them
    $partial = [];
    while ($b1->valid() && count($partial) < 3) {
        $partial[] = $b1->current();
        $b1->next();
    }
    expect($partial)->toBe([1, 2, 3]);

    // B2 reads everything
    expect(iterator_to_array($b2))->toBe([1, 2, 3, 4, 5]);

    // B1 continues from position 4 (next after 3)
    $remaining = [];
    while ($b1->valid()) {
        $remaining[] = $b1->current();
        $b1->next();
    }
    expect($remaining)->toBe([4, 5]);
});

// DATA PRESERVATION

test('all branches receive identical data', function () {
    $source = ['x', 'y', 'z'];
    [$b1, $b2, $b3] = Tee::split($source, 3);

    $r1 = iterator_to_array($b1);
    $r2 = iterator_to_array($b2);
    $r3 = iterator_to_array($b3);

    expect($r1)->toBe($r2);
    expect($r2)->toBe($r3);
    expect($r1)->toBe(['x', 'y', 'z']);
});

test('order is preserved across all branches', function () {
    $source = range(100, 110);
    [$b1, $b2] = Tee::split($source);

    expect(iterator_to_array($b1))->toBe(range(100, 110));
    expect(iterator_to_array($b2))->toBe(range(100, 110));
});

test('values are not modified during splitting', function () {
    $obj1 = (object)['id' => 1];
    $obj2 = (object)['id' => 2];
    $source = [$obj1, $obj2];

    [$b1, $b2] = Tee::split($source);

    $r1 = iterator_to_array($b1);
    $r2 = iterator_to_array($b2);

    expect($r1[0])->toBe($obj1);
    expect($r1[1])->toBe($obj2);
    expect($r2[0])->toBe($obj1);
    expect($r2[1])->toBe($obj2);
});
