<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\Tests\Support\FrozenClock;
use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Result\Result;

final class ToolUseCriterionCounter
{
    public function __construct(public int $calls = 0) {}

    public function increment(): void
    {
        $this->calls++;
    }
}

final class CountingToolUseCriterion implements CanDecideToContinue
{
    public function __construct(
        private ToolUseCriterionCounter $counter,
        private ContinuationDecision $result,
    ) {}

    #[\Override]
    public function decide(object $state): ContinuationDecision
    {
        $this->counter->increment();
        return $this->result;
    }
}

it('steps limit boundary works', function () {
    $state = new ToolUseState();
    $limit = new StepsLimit(1, static fn(ToolUseState $state): int => $state->stepCount());
    // Under limit - allow continuation (guard permits)
    expect($limit->decide($state))->toBe(ContinuationDecision::AllowContinuation);

    $state = $state->withAddedStep(new ToolUseStep());
    // At/over limit - forbid continuation (guard denies)
    expect($limit->decide($state))->toBe(ContinuationDecision::ForbidContinuation);
});

it('token usage limit boundary works', function () {
    $state = new ToolUseState();
    $state = $state->withAccumulatedUsage(new Usage(10, 0));
    $limit = new TokenUsageLimit(10, static fn(ToolUseState $state): int => $state->usage()->total());
    // At/over limit - forbid continuation
    expect($limit->decide($state))->toBe(ContinuationDecision::ForbidContinuation);
});

it('execution time limit uses clock deterministically', function () {
    $state = new ToolUseState();
    $start = $state->startedAt();
    $clock = new FrozenClock($start->modify('+61 seconds'));
    $limit = new ExecutionTimeLimit(60, static fn(ToolUseState $state) => $state->startedAt(), $clock);
    // Over limit - forbid continuation
    expect($limit->decide($state))->toBe(ContinuationDecision::ForbidContinuation);
});

it('tool call presence check reflects current step calls', function () {
    $state = new ToolUseState();
    $check = new ToolCallPresenceCheck(static fn(ToolUseState $state): bool => $state->currentStep()?->hasToolCalls() ?? false);
    // No tool calls - allow stop (work driver has no work)
    expect($check->decide($state))->toBe(ContinuationDecision::AllowStop);

    $state = $state->withCurrentStep(new ToolUseStep());
    // Still no tool calls
    expect($check->decide($state))->toBe(ContinuationDecision::AllowStop);

    $calls = new ToolCalls(new ToolCall('a', []));
    $state = $state->withCurrentStep(new ToolUseStep(toolCalls: $calls));
    // Has tool calls - request continuation (work driver has work)
    expect($check->decide($state))->toBe(ContinuationDecision::RequestContinuation);
});

it('error presence check stops on failures', function () {
    $state = new ToolUseState();
    $check = new ErrorPresenceCheck(static fn(ToolUseState $state): bool => $state->currentStep()?->hasErrors() ?? false);
    // No errors - allow continuation (guard permits)
    expect($check->decide($state))->toBe(ContinuationDecision::AllowContinuation);

    $execs = new ToolExecutions(
        new ToolExecution(new ToolCall('no', []), Result::failure(new \Exception('x')), new \DateTimeImmutable(), new \DateTimeImmutable())
    );
    $state = $state->withCurrentStep(new ToolUseStep(toolExecutions: $execs));
    // Has errors - forbid continuation (guard denies)
    expect($check->decide($state))->toBe(ContinuationDecision::ForbidContinuation);
});

it('tool use continuation criteria reports emptiness', function () {
    $criteria = new ContinuationCriteria();
    expect($criteria->isEmpty())->toBeTrue();
});

it('tool use continuation criteria uses priority resolution', function () {
    $counter = new ToolUseCriterionCounter();

    // ForbidContinuation wins over everything
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, ContinuationDecision::ForbidContinuation),
        new CountingToolUseCriterion($counter, ContinuationDecision::RequestContinuation),
    );

    expect($criteria->decide(new ToolUseState()))->toBe(ContinuationDecision::AllowStop);
    // Note: ContinuationCriteria.decide() returns AllowStop when resolution is false
    expect($counter->calls)->toBe(2);
});

it('tool use continuation criteria resolves RequestContinuation when no forbid', function () {
    $counter = new ToolUseCriterionCounter();

    // RequestContinuation wins when no ForbidContinuation
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, ContinuationDecision::AllowStop),
        new CountingToolUseCriterion($counter, ContinuationDecision::RequestContinuation),
    );

    expect($criteria->decide(new ToolUseState()))->toBe(ContinuationDecision::RequestContinuation);
});

it('tool use continuation criteria resolves AllowStop when all AllowStop', function () {
    $counter = new ToolUseCriterionCounter();

    // All AllowStop means nothing to do - resolves to AllowStop
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, ContinuationDecision::AllowStop),
        new CountingToolUseCriterion($counter, ContinuationDecision::AllowStop),
    );

    expect($criteria->decide(new ToolUseState()))->toBe(ContinuationDecision::AllowStop);
});

it('tool use continuation criteria withCriteria appends to set', function () {
    $counter = new ToolUseCriterionCounter();
    $criteria = new ContinuationCriteria(new CountingToolUseCriterion($counter, ContinuationDecision::AllowStop));
    $extended = $criteria->withCriteria(new CountingToolUseCriterion($counter, ContinuationDecision::RequestContinuation));

    // Original: just AllowStop → AllowStop (no work requested)
    expect($criteria->decide(new ToolUseState()))->toBe(ContinuationDecision::AllowStop);
    // Extended: AllowStop + RequestContinuation → RequestContinuation (work requested overrides stop)
    expect($extended->decide(new ToolUseState()))->toBe(ContinuationDecision::RequestContinuation);
});

it('evaluation returns allow stop for empty criteria', function () {
    $criteria = new ContinuationCriteria();
    $outcome = $criteria->evaluate(new ToolUseState());

    expect($outcome->decision)->toBe(ContinuationDecision::AllowStop);
    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->stopReason)->toBe(StopReason::Completed);
    expect($outcome->evaluations)->toBe([]);
});

it('evaluation returns forbid outcome when guard forbids', function () {
    $counter = new ToolUseCriterionCounter();
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, ContinuationDecision::ForbidContinuation),
        new CountingToolUseCriterion($counter, ContinuationDecision::RequestContinuation),
    );

    $outcome = $criteria->evaluate(new ToolUseState());

    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->decision)->toBe(ContinuationDecision::AllowStop);
    expect($outcome->getForbiddingCriterion())->toBe(CountingToolUseCriterion::class);
    expect($outcome->stopReason)->toBe(StopReason::GuardForbade);
});

it('evaluation returns request outcome when work is requested', function () {
    $counter = new ToolUseCriterionCounter();
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, ContinuationDecision::AllowStop),
        new CountingToolUseCriterion($counter, ContinuationDecision::RequestContinuation),
    );

    $outcome = $criteria->evaluate(new ToolUseState());

    expect($outcome->shouldContinue())->toBeTrue();
    expect($outcome->decision)->toBe(ContinuationDecision::RequestContinuation);
    expect($outcome->resolvedBy)->toBe(CountingToolUseCriterion::class);
});

it('evaluation includes all criterion evaluations', function () {
    $counter = new ToolUseCriterionCounter();
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, ContinuationDecision::AllowStop),
        new CountingToolUseCriterion($counter, ContinuationDecision::AllowStop),
    );

    $outcome = $criteria->evaluate(new ToolUseState());

    expect(count($outcome->evaluations))->toBe(2);
    expect($outcome->evaluations[0])->toBeInstanceOf(ContinuationEvaluation::class);
});

it('canContinue and decide delegate to evaluate', function () {
    $counter = new ToolUseCriterionCounter();
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, ContinuationDecision::RequestContinuation),
    );

    $state = new ToolUseState();
    $outcome = $criteria->evaluate($state);

    expect($criteria->canContinue($state))->toBe($outcome->shouldContinue());
    expect($criteria->decide($state))->toBe($outcome->decision);
});
