<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Events\ContinuationEvaluated;

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
