<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Stop\ExecutionContinuation;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Core\Stop\StopSignals;
use Cognesy\Agents\Events\ContinuationEvaluated;

it('renders continuation evaluated event summary', function () {
    $signal = new StopSignal(
        reason: StopReason::StepsLimitReached,
        message: 'Steps limit reached',
        source: 'Guard',
    );
    $continuation = new ExecutionContinuation(
        stopSignals: new StopSignals($signal),
    );
    $event = new ContinuationEvaluated(
        agentId: 'agent-12345678',
        parentAgentId: null,
        stepNumber: 2,
        continuation: $continuation,
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
    $continuation = new ExecutionContinuation(
        stopSignals: new StopSignals($signal),
    );

    $event = new ContinuationEvaluated(
        agentId: 'agent-12345678',
        parentAgentId: null,
        stepNumber: 1,
        continuation: $continuation,
    );

    expect($event->stopReason())->toBe(StopReason::TokenLimitReached)
        ->and($event->resolvedBy())->toBe('CustomSource');
});
