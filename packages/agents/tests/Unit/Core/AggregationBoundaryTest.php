<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;

// ContinuationOutcome::isForbidden() tests
test('ContinuationOutcome::isForbidden returns true when ForbidContinuation present', function () {
    $evaluation = ContinuationEvaluation::fromDecision(
        criterionClass: 'TestGuard',
        decision: ContinuationDecision::ForbidContinuation,
        stopReason: StopReason::StepsLimitReached,
    );

    $outcome = ContinuationOutcome::fromEvaluations([$evaluation]);

    expect($outcome->isForbidden())->toBeTrue();
    expect($outcome->shouldContinue())->toBeFalse();
});

test('ContinuationOutcome::isForbidden returns false when AllowContinuation only', function () {
    $evaluation = ContinuationEvaluation::fromDecision(
        criterionClass: 'TestGuard',
        decision: ContinuationDecision::AllowContinuation,
    );

    $outcome = ContinuationOutcome::fromEvaluations([$evaluation]);

    expect($outcome->isForbidden())->toBeFalse();
    // AllowContinuation with no AllowStop = bootstrap case, continues
    expect($outcome->shouldContinue())->toBeTrue();
});

test('ContinuationOutcome::isForbidden returns false when RequestContinuation present', function () {
    $evaluation = ContinuationEvaluation::fromDecision(
        criterionClass: 'TestWork',
        decision: ContinuationDecision::RequestContinuation,
    );

    $outcome = ContinuationOutcome::fromEvaluations([$evaluation]);

    expect($outcome->isForbidden())->toBeFalse();
    expect($outcome->shouldContinue())->toBeTrue();
});

test('ForbidContinuation takes precedence over RequestContinuation', function () {
    $forbid = ContinuationEvaluation::fromDecision(
        criterionClass: 'TestGuard',
        decision: ContinuationDecision::ForbidContinuation,
        stopReason: StopReason::TokenLimitReached,
    );
    $request = ContinuationEvaluation::fromDecision(
        criterionClass: 'TestWork',
        decision: ContinuationDecision::RequestContinuation,
    );

    $outcome = ContinuationOutcome::fromEvaluations([$request, $forbid]);

    expect($outcome->isForbidden())->toBeTrue();
    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->stopReason())->toBe(StopReason::TokenLimitReached);
});

// Precomputed outcome in pending state
test('AgentState stores pending outcome', function () {
    $state = (new AgentState())->withNewStepExecution();

    $outcome = ContinuationOutcome::fromEvaluations([
        ContinuationEvaluation::fromDecision('Test', ContinuationDecision::AllowContinuation),
    ]);

    $state = $state->withContinuationOutcome($outcome);

    expect($state->pendingOutcome())->toBe($outcome);
    expect($state->continuationOutcome())->toBe($outcome);
});

// Evaluation aggregation tests
test('Multiple AllowContinuation evaluations with AllowStop result in stop', function () {
    $evals = [
        ContinuationEvaluation::fromDecision('Guard1', ContinuationDecision::AllowContinuation),
        ContinuationEvaluation::fromDecision('Guard2', ContinuationDecision::AllowContinuation),
        ContinuationEvaluation::fromDecision('WorkDriver', ContinuationDecision::AllowStop),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evals);

    expect($outcome->decision())->toBe(ContinuationDecision::AllowStop);
    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->isForbidden())->toBeFalse();
});

test('Mixed evaluations with RequestContinuation results in continuation', function () {
    $evals = [
        ContinuationEvaluation::fromDecision('Guard1', ContinuationDecision::AllowContinuation),
        ContinuationEvaluation::fromDecision('Work', ContinuationDecision::RequestContinuation),
        ContinuationEvaluation::fromDecision('Guard2', ContinuationDecision::AllowContinuation),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evals);

    expect($outcome->decision())->toBe(ContinuationDecision::RequestContinuation);
    expect($outcome->shouldContinue())->toBeTrue();
    expect($outcome->isForbidden())->toBeFalse();
});

// Precomputed outcome authority tests
test('Precomputed outcome with ForbidContinuation is respected by shouldContinue flow', function () {
    $state = (new AgentState())->withNewStepExecution();

    // Simulate guard writing ForbidContinuation
    $forbidEval = ContinuationEvaluation::fromDecision(
        criterionClass: 'TestGuard',
        decision: ContinuationDecision::ForbidContinuation,
        stopReason: StopReason::StepsLimitReached,
    );

    $outcome = ContinuationOutcome::fromEvaluations([$forbidEval]);
    $state = $state->withContinuationOutcome($outcome);

    // Verify outcome is stored and accessible
    $storedOutcome = $state->pendingOutcome();
    expect($storedOutcome)->not->toBeNull();
    expect($storedOutcome->shouldContinue())->toBeFalse();
    expect($storedOutcome->isForbidden())->toBeTrue();
});

test('Precomputed outcome with RequestContinuation allows continuation', function () {
    $state = (new AgentState())->withNewStepExecution();

    // Simulate work driver requesting continuation
    $requestEval = ContinuationEvaluation::fromDecision(
        criterionClass: 'TestWorkDriver',
        decision: ContinuationDecision::RequestContinuation,
    );

    $outcome = ContinuationOutcome::fromEvaluations([$requestEval]);
    $state = $state->withContinuationOutcome($outcome);

    $storedOutcome = $state->pendingOutcome();
    expect($storedOutcome)->not->toBeNull();
    expect($storedOutcome->shouldContinue())->toBeTrue();
    expect($storedOutcome->isForbidden())->toBeFalse();
});
