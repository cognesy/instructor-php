<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Events\ContinuationEvaluated;

it('renders continuation evaluated event summary', function () {
    $signal = new StopSignal(
        reason: StopReason::StepsLimitReached,
        message: 'Steps limit reached',
        source: 'Guard',
    );
    $event = new ContinuationEvaluated(
        agentId: 'agent-12345678',
        parentAgentId: null,
        stepNumber: 2,
        stopSignal: $signal,
    );

    expect((string) $event)->toContain('STOP');
    expect((string) $event)->toContain('steps_limit');
});

it('uses stop signal metadata when provided', function () {
    $signal = new StopSignal(
        reason: StopReason::TokenLimitReached,
        message: 'Token limit reached',
        source: 'CustomSource',
    );

    $event = new ContinuationEvaluated(
        agentId: 'agent-12345678',
        parentAgentId: null,
        stepNumber: 1,
        stopSignal: $signal,
    );

    expect($event->stopReason())->toBe(StopReason::TokenLimitReached)
        ->and($event->resolvedBy())->toBe('CustomSource');
});
