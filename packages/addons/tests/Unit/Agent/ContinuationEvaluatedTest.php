<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Events\ContinuationEvaluated;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

it('renders continuation evaluated event summary', function () {
    $outcome = new ContinuationOutcome(
        decision: ContinuationDecision::AllowStop,
        shouldContinue: false,
        resolvedBy: 'LimitCriterion',
        stopReason: StopReason::StepsLimitReached,
        evaluations: [],
    );

    $event = new ContinuationEvaluated(
        agentId: 'agent-12345678',
        parentAgentId: null,
        stepNumber: 2,
        outcome: $outcome,
    );

    expect($event->outcome)->toBe($outcome);
    expect((string) $event)->toContain('STOP');
    expect((string) $event)->toContain('steps_limit');
});
