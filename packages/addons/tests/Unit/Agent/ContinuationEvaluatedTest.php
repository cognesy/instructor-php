<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Events\ContinuationEvaluated;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

it('renders continuation evaluated event summary', function () {
    // Create evaluations that result in ForbidContinuation to trigger StepsLimitReached stop reason
    $evaluations = [
        new ContinuationEvaluation(
            criterionClass: StepsLimit::class,
            decision: ContinuationDecision::ForbidContinuation,
            reason: 'Steps limit reached',
            stopReason: StopReason::StepsLimitReached,
        ),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);

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
