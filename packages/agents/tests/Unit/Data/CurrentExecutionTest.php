<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Data;

use Cognesy\Agents\Core\Data\CurrentExecution;
use DateTimeImmutable;

test('creates with step number and generates id', function () {
    $exec = new CurrentExecution(stepNumber: 5);

    expect($exec->stepNumber)->toBe(5);
    expect($exec->id)->not->toBeEmpty();
    expect($exec->startedAt)->toBeInstanceOf(DateTimeImmutable::class);
});

test('preserves explicit id', function () {
    $exec = new CurrentExecution(stepNumber: 1, id: 'custom-id');

    expect($exec->id)->toBe('custom-id');
});

test('toArray includes core fields only', function () {
    $exec = new CurrentExecution(stepNumber: 5, id: 'test-id');

    $array = $exec->toArray();

    expect($array)->toHaveKeys(['id', 'stepNumber', 'startedAt']);
});

test('fromArray restores core fields', function () {
    $data = [
        'id' => 'restored-id',
        'stepNumber' => 7,
        'startedAt' => '2026-01-28T10:00:00+00:00',
    ];

    $exec = CurrentExecution::fromArray($data);

    expect($exec->id)->toBe('restored-id');
    expect($exec->stepNumber)->toBe(7);
});
