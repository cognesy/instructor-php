<?php declare(strict_types=1);

use Cognesy\Stream\Support\Tee;

// LARGE DATASETS

test('handles 10000 element array split 2 ways', function () {
    $source = range(1, 10000);
    [$b1, $b2] = Tee::split($source);

    $count1 = 0;
    foreach ($b1 as $val) $count1++;

    $count2 = 0;
    foreach ($b2 as $val) $count2++;

    expect($count1)->toBe(10000);
    expect($count2)->toBe(10000);
});

test('handles 10000 element array split 10 ways', function () {
    $source = range(1, 10000);
    $branches = Tee::split($source, 10);

    expect($branches)->toHaveCount(10);

    foreach ($branches as $branch) {
        $count = 0;
        foreach ($branch as $val) $count++;
        expect($count)->toBe(10000);
    }
});

test('memory usage stays reasonable with divergent branches', function () {
    $source = range(1, 5000);
    [$fast, $slow] = Tee::split($source);

    // Fast reads 4000, slow reads 100 (buffer = ~3900)
    for ($i = 0; $i < 4000; $i++) {
        $fast->current();
        $fast->next();
    }

    for ($i = 0; $i < 100; $i++) {
        $slow->current();
        $slow->next();
    }

    // Should still be functional
    expect($fast->valid())->toBeTrue();
    expect($slow->valid())->toBeTrue();

    // Complete both (use while loop after manual iteration)
    $fastCount = 0;
    while ($fast->valid()) {
        $fastCount++;
        $fast->next();
    }
    expect($fastCount)->toBe(1000);

    $slowCount = 0;
    while ($slow->valid()) {
        $slowCount++;
        $slow->next();
    }
    expect($slowCount)->toBe(4900);
});

// SIDE EFFECTS

test('generator with side effects executes once per value', function () {
    $executed = [];

    $generator = function () use (&$executed) {
        for ($i = 1; $i <= 5; $i++) {
            $executed[] = $i;
            yield $i;
        }
    };

    [$b1, $b2] = Tee::split($generator());

    // Consume both branches
    iterator_to_array($b1);
    iterator_to_array($b2);

    // Generator should execute each value exactly once
    // Note: Due to iterator initialization, first 2 values are consumed early
    expect($executed)->toHaveCount(5);
    expect($executed)->toBe([1, 2, 3, 4, 5]);
});

test('object modifications visible across branches', function () {
    $obj = (object)['counter' => 0];
    $source = [$obj, $obj, $obj];

    [$b1, $b2] = Tee::split($source);

    // Consume B1 and modify objects
    $results1 = [];
    foreach ($b1 as $item) {
        $item->counter++;
        $results1[] = $item->counter;
    }

    // B2 sees the modifications (same object references)
    $results2 = [];
    foreach ($b2 as $item) {
        $results2[] = $item->counter;
    }

    expect($results1)->toBe([1, 2, 3]);
    expect($results2)->toBe([3, 3, 3]); // All point to same object with counter=3
});

test('resource cleanup happens correctly', function () {
    $opened = [];
    $closed = [];

    $generator = function () use (&$opened, &$closed) {
        for ($i = 1; $i <= 3; $i++) {
            $resource = (object)['id' => $i];
            $opened[] = $i;
            try {
                yield $resource;
            } finally {
                $closed[] = $i;
            }
        }
    };

    [$b1, $b2] = Tee::split($generator());

    // Consume both
    iterator_to_array($b1);
    iterator_to_array($b2);

    // All resources should be opened and closed once
    expect($opened)->toHaveCount(3);
    expect($closed)->toHaveCount(3);
});

// REAL-WORLD SCENARIOS

test('process CSV data in multiple ways', function () {
    // Simulate CSV rows
    $csvData = [
        ['name' => 'Alice', 'age' => 30, 'city' => 'NYC'],
        ['name' => 'Bob', 'age' => 25, 'city' => 'LA'],
        ['name' => 'Charlie', 'age' => 35, 'city' => 'NYC'],
    ];

    [$namesStream, $agesStream, $citiesStream] = Tee::split($csvData, 3);

    // Extract names
    $names = [];
    foreach ($namesStream as $row) {
        $names[] = $row['name'];
    }

    // Extract ages
    $ages = [];
    foreach ($agesStream as $row) {
        $ages[] = $row['age'];
    }

    // Extract cities
    $cities = [];
    foreach ($citiesStream as $row) {
        $cities[] = $row['city'];
    }

    expect($names)->toBe(['Alice', 'Bob', 'Charlie']);
    expect($ages)->toBe([30, 25, 35]);
    expect($cities)->toBe(['NYC', 'LA', 'NYC']);
});

test('duplicate stream for logging and processing', function () {
    $source = range(1, 10);
    [$logger, $processor] = Tee::split($source);

    // Logger records all values
    $log = [];
    foreach ($logger as $val) {
        $log[] = "Logged: $val";
    }

    // Processor does computation
    $results = [];
    foreach ($processor as $val) {
        $results[] = $val * 2;
    }

    expect($log)->toHaveCount(10);
    expect($results)->toBe([2, 4, 6, 8, 10, 12, 14, 16, 18, 20]);
});

test('fan-out pattern one fast reader multiple slow processors', function () {
    $source = range(1, 20);
    [$reader, $slow1, $slow2, $slow3] = Tee::split($source, 4);

    // Reader consumes quickly
    $readerData = iterator_to_array($reader);
    expect($readerData)->toHaveCount(20);

    // Slow processors work at their own pace
    $process1 = [];
    foreach ($slow1 as $val) {
        if ($val % 2 === 0) $process1[] = $val;
    }

    $process2 = [];
    foreach ($slow2 as $val) {
        if ($val % 3 === 0) $process2[] = $val;
    }

    $process3 = [];
    foreach ($slow3 as $val) {
        if ($val % 5 === 0) $process3[] = $val;
    }

    expect($process1)->toBe([2, 4, 6, 8, 10, 12, 14, 16, 18, 20]);
    expect($process2)->toBe([3, 6, 9, 12, 15, 18]);
    expect($process3)->toBe([5, 10, 15, 20]);
});

test('broadcast pattern multiple independent consumers', function () {
    $events = [
        ['type' => 'login', 'user' => 'alice'],
        ['type' => 'purchase', 'user' => 'bob', 'amount' => 100],
        ['type' => 'logout', 'user' => 'alice'],
        ['type' => 'purchase', 'user' => 'alice', 'amount' => 50],
    ];

    [$analytics, $notifications, $audit] = Tee::split($events, 3);

    // Analytics: count purchases
    $purchaseCount = 0;
    foreach ($analytics as $event) {
        if ($event['type'] === 'purchase') {
            $purchaseCount++;
        }
    }

    // Notifications: extract user actions
    $userActions = [];
    foreach ($notifications as $event) {
        $userActions[] = $event['user'] . ':' . $event['type'];
    }

    // Audit: log all events
    $auditLog = [];
    foreach ($audit as $event) {
        $auditLog[] = $event['type'];
    }

    expect($purchaseCount)->toBe(2);
    expect($userActions)->toBe(['alice:login', 'bob:purchase', 'alice:logout', 'alice:purchase']);
    expect($auditLog)->toBe(['login', 'purchase', 'logout', 'purchase']);
});

test('handles varying processing speeds gracefully', function () {
    $source = range(1, 100);
    [$instant, $fast, $medium, $slow] = Tee::split($source, 4);

    // Instant consumer
    $instantResult = iterator_to_array($instant);
    expect(count($instantResult))->toBe(100);

    // Fast consumer (processes every item)
    $fastResult = [];
    foreach ($fast as $val) {
        $fastResult[] = $val * 2;
    }
    expect(count($fastResult))->toBe(100);

    // Medium consumer (skips some)
    $mediumResult = [];
    foreach ($medium as $val) {
        if ($val % 2 === 0) {
            $mediumResult[] = $val;
        }
    }
    expect(count($mediumResult))->toBe(50);

    // Slow consumer (very selective)
    $slowResult = [];
    foreach ($slow as $val) {
        if ($val % 10 === 0) {
            $slowResult[] = $val;
        }
    }
    expect(count($slowResult))->toBe(10);
});

test('pipeline with early exit on condition', function () {
    $source = range(1, 1000);
    [$validator, $processor] = Tee::split($source);

    // Validator looks for a condition and exits early
    $found = null;
    foreach ($validator as $val) {
        if ($val > 100 && $val % 7 === 0) {
            $found = $val;
            break;
        }
    }

    expect($found)->toBe(105);

    // Processor continues independently
    $sum = 0;
    foreach ($processor as $val) {
        $sum += $val;
        if ($val === 100) break; // Different exit condition
    }

    expect($sum)->toBe(5050); // Sum of 1 to 100
});
