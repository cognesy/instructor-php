<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\ExecutionState;
use Cognesy\Agents\Events\ContinuationEvaluated;

it('renders continuation evaluated event summary', function () {
    $signal = new StopSignal(
        reason: StopReason::StepsLimitReached,
        message: 'Steps limit reached',
        source: 'Guard',
    );
    $executionState = ExecutionState::fresh()->withStopSignal($signal);
    $event = new ContinuationEvaluated(
        agentId: 'agent-12345678',
        parentAgentId: null,
        stepNumber: 2,
        executionState: $executionState,
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
    $executionState = ExecutionState::fresh()->withStopSignal($signal);

    $event = new ContinuationEvaluated(
        agentId: 'agent-12345678',
        parentAgentId: null,
        stepNumber: 1,
        executionState: $executionState,
    );

    expect($event->stopReason())->toBe(StopReason::TokenLimitReached)
        ->and($event->resolvedBy())->toBe('CustomSource');
});
