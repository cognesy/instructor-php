<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Agents\Agent\Data\StateInfo;

it('accumulates execution time', function () {
    $info = StateInfo::new();
    $first = $info->addExecutionTime(1.5);
    $second = $first->addExecutionTime(2.25);

    expect($info->cumulativeExecutionSeconds())->toBe(0.0);
    expect($first->cumulativeExecutionSeconds())->toBe(1.5);
    expect($second->cumulativeExecutionSeconds())->toBe(3.75);
});

it('serializes and deserializes cumulative execution time', function () {
    $info = StateInfo::new()->addExecutionTime(4.5);
    $payload = $info->toArray();
    $restored = StateInfo::fromArray($payload);

    expect($restored->cumulativeExecutionSeconds())->toBe(4.5);
});

it('defaults cumulative execution time when missing from payload', function () {
    $payload = [
        'id' => 'state-1',
        'startedAt' => '2026-01-01T00:00:00+00:00',
        'updatedAt' => '2026-01-01T01:00:00+00:00',
    ];

    $restored = StateInfo::fromArray($payload);

    expect($restored->cumulativeExecutionSeconds())->toBe(0.0);
});
