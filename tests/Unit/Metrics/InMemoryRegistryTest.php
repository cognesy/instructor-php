<?php declare(strict_types=1);

use Cognesy\Metrics\Data\Tags;
use Cognesy\Metrics\Registry\InMemoryRegistry;

test('stores and retrieves counters', function () {
    $registry = new InMemoryRegistry();

    $counter = $registry->counter('requests', Tags::of(['method' => 'GET']), 1);
    $registry->counter('requests', Tags::of(['method' => 'POST']), 2);

    expect($registry->count())->toBe(2);
    expect(iterator_to_array($registry->all()))->toHaveCount(2);
});

test('finds metrics by name', function () {
    $registry = new InMemoryRegistry();

    $registry->counter('requests', Tags::of(['method' => 'GET']), 1);
    $registry->gauge('memory', Tags::of([]), 1024.0);
    $registry->counter('requests', Tags::of(['method' => 'POST']), 1);

    $found = iterator_to_array($registry->find('requests'));

    expect($found)->toHaveCount(2);
    expect($found[0]->name())->toBe('requests');
    expect($found[1]->name())->toBe('requests');
});

test('finds metrics by name and tags', function () {
    $registry = new InMemoryRegistry();

    $registry->counter('requests', Tags::of(['method' => 'GET']), 1);
    $registry->counter('requests', Tags::of(['method' => 'POST']), 1);

    $found = iterator_to_array($registry->find('requests', Tags::of(['method' => 'GET'])));

    expect($found)->toHaveCount(1);
    expect($found[0]->value())->toBe(1.0);
});

test('clears all metrics', function () {
    $registry = new InMemoryRegistry();

    $registry->counter('requests', Tags::of([]), 1);
    $registry->gauge('memory', Tags::of([]), 1024.0);

    expect($registry->count())->toBe(2);

    $registry->clear();

    expect($registry->count())->toBe(0);
    expect(iterator_to_array($registry->all()))->toBeEmpty();
});

test('returns metric instances from recording methods', function () {
    $registry = new InMemoryRegistry();

    $counter = $registry->counter('requests', Tags::of([]), 1);
    $gauge = $registry->gauge('memory', Tags::of([]), 1024.0);
    $histogram = $registry->histogram('latency', Tags::of([]), 150.0);
    $timer = $registry->timer('duration', Tags::of([]), 500.0);

    expect($counter)->toBeInstanceOf(\Cognesy\Metrics\Data\Counter::class);
    expect($gauge)->toBeInstanceOf(\Cognesy\Metrics\Data\Gauge::class);
    expect($histogram)->toBeInstanceOf(\Cognesy\Metrics\Data\Histogram::class);
    expect($timer)->toBeInstanceOf(\Cognesy\Metrics\Data\Timer::class);
});
