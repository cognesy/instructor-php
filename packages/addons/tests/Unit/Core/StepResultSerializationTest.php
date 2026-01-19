<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\Step\StepResult;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

function makeOutcome(): ContinuationOutcome {
    $evaluation = new ContinuationEvaluation(
        criterionClass: StepsLimit::class,
        decision: ContinuationDecision::ForbidContinuation,
        reason: 'step limit reached',
        context: ['limit' => 1],
        stopReason: StopReason::StepsLimitReached,
    );

    return ContinuationOutcome::fromEvaluations([$evaluation]);
}

it('round-trips AgentState step results', function () {
    $stepResult = new StepResult(new AgentStep(), makeOutcome());
    $state = AgentState::empty()->recordStepResult($stepResult);

    $restored = AgentState::fromArray($state->toArray());

    expect($restored->stepCount())->toBe(1);
    expect($restored->lastStepResult()?->step)->toBeInstanceOf(AgentStep::class);
    expect($restored->continuationOutcome()?->shouldContinue())->toBeFalse();
    expect($restored->stopReason())->toBe(StopReason::StepsLimitReached);
});

it('round-trips ToolUseState step results', function () {
    $stepResult = new StepResult(new ToolUseStep(), makeOutcome());
    $state = (new ToolUseState())->recordStepResult($stepResult);

    $restored = ToolUseState::fromArray($state->toArray());

    expect($restored->stepCount())->toBe(1);
    expect($restored->lastStepResult()?->step)->toBeInstanceOf(ToolUseStep::class);
    expect($restored->continuationOutcome()?->shouldContinue())->toBeFalse();
    expect($restored->stopReason())->toBe(StopReason::StepsLimitReached);
});

it('round-trips ChatState step results', function () {
    $stepResult = new StepResult(new ChatStep('tester'), makeOutcome());
    $state = (new ChatState())->recordStepResult($stepResult);

    $restored = ChatState::fromArray($state->toArray());

    expect($restored->stepCount())->toBe(1);
    expect($restored->lastStepResult()?->step)->toBeInstanceOf(ChatStep::class);
    expect($restored->continuationOutcome()?->shouldContinue())->toBeFalse();
    expect($restored->stopReason())->toBe(StopReason::StepsLimitReached);
});

it('round-trips CollaborationState step results', function () {
    $stepResult = new StepResult(new CollaborationStep('tester'), makeOutcome());
    $state = (new CollaborationState())->recordStepResult($stepResult);

    $restored = CollaborationState::fromArray($state->toArray());

    expect($restored->stepCount())->toBe(1);
    expect($restored->lastStepResult()?->step)->toBeInstanceOf(CollaborationStep::class);
    expect($restored->continuationOutcome()?->shouldContinue())->toBeFalse();
    expect($restored->stopReason())->toBe(StopReason::StepsLimitReached);
});
