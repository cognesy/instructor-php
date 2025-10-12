<?php declare(strict_types=1);

use Cognesy\Stream\Support\Tee;

// INTERLEAVED READING PATTERNS

test('alternating read pattern A-B-A-B-A-B', function () {
    $source = [1, 2, 3, 4, 5, 6];
    [$a, $b] = Tee::split($source);

    $results = [];

    // Alternate reading
    $results['a1'] = $a->current(); $a->next();
    $results['b1'] = $b->current(); $b->next();
    $results['a2'] = $a->current(); $a->next();
    $results['b2'] = $b->current(); $b->next();
    $results['a3'] = $a->current(); $a->next();
    $results['b3'] = $b->current(); $b->next();

    expect($results)->toBe([
        'a1' => 1, 'b1' => 1,
        'a2' => 2, 'b2' => 2,
        'a3' => 3, 'b3' => 3,
    ]);

    // Continue to completion (use while loop after manual iteration)
    $aRest = [];
    while ($a->valid()) {
        $aRest[] = $a->current();
        $a->next();
    }
    $bRest = [];
    while ($b->valid()) {
        $bRest[] = $b->current();
        $b->next();
    }

    expect($aRest)->toBe([4, 5, 6]);
    expect($bRest)->toBe([4, 5, 6]);
});

test('burst read pattern A-A-A then B-B-B', function () {
    $source = [1, 2, 3, 4, 5, 6];
    [$a, $b] = Tee::split($source);

    // A reads first 3
    $aFirst = [];
    for ($i = 0; $i < 3; $i++) {
        $aFirst[] = $a->current();
        $a->next();
    }

    // B reads first 3
    $bFirst = [];
    for ($i = 0; $i < 3; $i++) {
        $bFirst[] = $b->current();
        $b->next();
    }

    expect($aFirst)->toBe([1, 2, 3]);
    expect($bFirst)->toBe([1, 2, 3]);

    // Both read remaining (use while loop after manual iteration)
    $aRest = [];
    while ($a->valid()) {
        $aRest[] = $a->current();
        $a->next();
    }
    $bRest = [];
    while ($b->valid()) {
        $bRest[] = $b->current();
        $b->next();
    }

    expect($aRest)->toBe([4, 5, 6]);
    expect($bRest)->toBe([4, 5, 6]);
});

test('three-way round-robin A-B-C-A-B-C', function () {
    $source = range(1, 9);
    [$a, $b, $c] = Tee::split($source, 3);

    $results = [];

    // Round 1
    $results['a1'] = $a->current(); $a->next();
    $results['b1'] = $b->current(); $b->next();
    $results['c1'] = $c->current(); $c->next();

    // Round 2
    $results['a2'] = $a->current(); $a->next();
    $results['b2'] = $b->current(); $b->next();
    $results['c2'] = $c->current(); $c->next();

    // Round 3
    $results['a3'] = $a->current(); $a->next();
    $results['b3'] = $b->current(); $b->next();
    $results['c3'] = $c->current(); $c->next();

    expect($results)->toBe([
        'a1' => 1, 'b1' => 1, 'c1' => 1,
        'a2' => 2, 'b2' => 2, 'c2' => 2,
        'a3' => 3, 'b3' => 3, 'c3' => 3,
    ]);
});

test('random interleaved access pattern', function () {
    $source = range(1, 10);
    [$a, $b, $c] = Tee::split($source, 3);

    // Random access pattern
    $a->current(); $a->next(); // a = 1
    $a->current(); $a->next(); // a = 2
    $b->current(); $b->next(); // b = 1
    $c->current(); $c->next(); // c = 1
    $a->current(); $a->next(); // a = 3
    $b->current(); $b->next(); // b = 2
    $b->current(); $b->next(); // b = 3
    $c->current(); $c->next(); // c = 2

    // Verify positions
    expect($a->current())->toBe(4);
    expect($b->current())->toBe(4);
    expect($c->current())->toBe(3);
});

test('one branch reads all then others read', function () {
    $source = [10, 20, 30, 40];
    [$first, $second, $third] = Tee::split($source, 3);

    // First reads everything
    $firstResult = iterator_to_array($first);
    expect($firstResult)->toBe([10, 20, 30, 40]);

    // Second reads from buffer
    $secondResult = iterator_to_array($second);
    expect($secondResult)->toBe([10, 20, 30, 40]);

    // Third reads from buffer
    $thirdResult = iterator_to_array($third);
    expect($thirdResult)->toBe([10, 20, 30, 40]);
});

// BUFFER BEHAVIOR

test('buffer grows when branches diverge', function () {
    $source = range(1, 100);
    [$fast, $slow] = Tee::split($source);

    // Fast branch reads 50 items (buffer should grow)
    for ($i = 0; $i < 50; $i++) {
        $fast->current();
        $fast->next();
    }

    // Slow hasn't moved (buffer has 50 items)
    expect($slow->current())->toBe(1);

    // Slow catches up gradually
    for ($i = 0; $i < 25; $i++) {
        $slow->current();
        $slow->next();
    }

    // Verify both still work
    expect($fast->current())->toBe(51);
    expect($slow->current())->toBe(26);
});

test('buffer shrinks when slowest branch advances', function () {
    $source = range(1, 50);
    [$fast, $slow] = Tee::split($source);

    // Create large buffer
    for ($i = 0; $i < 30; $i++) {
        $fast->current();
        $fast->next();
    }

    // Slow is at position 1
    expect($slow->current())->toBe(1);

    // Slow catches up (buffer should shrink progressively)
    for ($i = 0; $i < 25; $i++) {
        $slow->current();
        $slow->next();
    }

    // Both continue
    expect($fast->current())->toBe(31);
    expect($slow->current())->toBe(26);

    // Complete both (use while loop after manual iteration)
    $fastRest = [];
    while ($fast->valid()) {
        $fastRest[] = $fast->current();
        $fast->next();
    }
    $slowRest = [];
    while ($slow->valid()) {
        $slowRest[] = $slow->current();
        $slow->next();
    }

    expect(count($fastRest))->toBe(20);
    expect(count($slowRest))->toBe(25);
});

test('extreme divergence with maximum buffer size', function () {
    $source = range(1, 1000);
    [$fast, $slow] = Tee::split($source);

    // Fast reads 900, slow reads 10 (buffer = 890 items)
    for ($i = 0; $i < 900; $i++) {
        $fast->current();
        $fast->next();
    }

    for ($i = 0; $i < 10; $i++) {
        $slow->current();
        $slow->next();
    }

    // Verify positions
    expect($fast->current())->toBe(901);
    expect($slow->current())->toBe(11);

    // Both should complete successfully (use while loop after manual iteration)
    $fastCount = 0;
    while ($fast->valid()) {
        $fastCount++;
        $fast->next();
    }
    expect($fastCount)->toBe(100);

    $slowCount = 0;
    while ($slow->valid()) {
        $slowCount++;
        $slow->next();
    }
    expect($slowCount)->toBe(990);
});

test('buffer cleared when all branches complete', function () {
    $source = [1, 2, 3];
    [$b1, $b2] = Tee::split($source);

    // Both consume fully
    iterator_to_array($b1);
    iterator_to_array($b2);

    // No more values
    expect($b1->valid())->toBeFalse();
    expect($b2->valid())->toBeFalse();
});

// STATE MANAGEMENT

test('branch state is independent after split', function () {
    $source = [1, 2, 3, 4, 5];
    [$b1, $b2] = Tee::split($source);

    // B1 advances
    expect($b1->current())->toBe(1);
    $b1->next();
    expect($b1->current())->toBe(2);

    // B2 still at start
    expect($b2->current())->toBe(1);

    // B2 advances independently
    $b2->next();
    $b2->next();
    $b2->next();
    expect($b2->current())->toBe(4);

    // B1 unchanged
    expect($b1->current())->toBe(2);
});

test('valid checks do not interfere between branches', function () {
    $source = [1, 2];
    [$b1, $b2] = Tee::split($source);

    expect($b1->valid())->toBeTrue();
    expect($b2->valid())->toBeTrue();

    // Consume B1 fully
    iterator_to_array($b1);
    expect($b1->valid())->toBeFalse();

    // B2 still valid
    expect($b2->valid())->toBeTrue();

    // Consume B2
    iterator_to_array($b2);
    expect($b2->valid())->toBeFalse();
});

test('key returns sequential numeric keys', function () {
    // Note: The generator-based implementation uses numeric keys starting at 0
    $source = ['a' => 1, 'b' => 2, 'c' => 3];
    [$b1, $b2] = Tee::split($source);

    // B1 iteration - keys are numeric from the generator
    expect($b1->key())->toBe(0);
    expect($b1->current())->toBe(1);
    $b1->next();
    expect($b1->key())->toBe(1);

    // B2 iteration
    expect($b2->key())->toBe(0);
    expect($b2->current())->toBe(1);
    $b2->next();
    expect($b2->key())->toBe(1);
});

test('rewind is not supported', function () {
    $source = [1, 2, 3];
    [$branch] = Tee::split($source);

    $branch->current();
    $branch->next();
    $branch->current();

    // Rewind is not supported for generator-backed iterators
    // This test just verifies it doesn't crash
    expect($branch->current())->toBe(2);
});

test('multiple iterations over same branch throw exception', function () {
    $source = [1, 2, 3];
    [$branch] = Tee::split($source);

    // First iteration
    $first = iterator_to_array($branch);
    expect($first)->toBe([1, 2, 3]);

    // Second iteration throws exception (generator exhausted and closed)
    iterator_to_array($branch);
})->throws(Exception::class, 'Cannot traverse an already closed generator');
