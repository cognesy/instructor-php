<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;
use DateTimeImmutable;

it('touches updatedAt on state changes while keeping createdAt', function () {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-01T01:00:00+00:00');
    $state = new AgentState(createdAt: $createdAt, updatedAt: $updatedAt);

    $next = $state->withMessages(Messages::fromString('ping'));

    expect($next->createdAt()->format(DateTimeImmutable::ATOM))->toBe('2026-01-01T00:00:00+00:00')
        ->and($next->updatedAt()->getTimestamp())->toBeGreaterThan($state->updatedAt()->getTimestamp());
});

it('serializes and deserializes state timestamps', function () {
    $createdAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T01:00:00+00:00');
    $state = new AgentState(createdAt: $createdAt, updatedAt: $updatedAt);

    $restored = AgentState::fromArray($state->toArray());

    expect($restored->createdAt()->format(DateTimeImmutable::ATOM))->toBe('2026-01-02T00:00:00+00:00')
        ->and($restored->updatedAt()->format(DateTimeImmutable::ATOM))->toBe('2026-01-02T01:00:00+00:00');
});
