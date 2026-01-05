<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
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
        private bool $result,
    ) {}

    public function canContinue(object $state): bool
    {
        $this->counter->increment();
        return $this->result;
    }
}

it('steps limit boundary works', function () {
    $state = new ToolUseState();
    $limit = new StepsLimit(1, static fn(ToolUseState $state): int => $state->stepCount());
    expect($limit->canContinue($state))->toBeTrue();

    $state = $state->withAddedStep(new ToolUseStep());
    expect($limit->canContinue($state))->toBeFalse();
});

it('token usage limit boundary works', function () {
    $state = new ToolUseState();
    $state = $state->withAccumulatedUsage(new Usage(10, 0));
    $limit = new TokenUsageLimit(10, static fn(ToolUseState $state): int => $state->usage()->total());
    expect($limit->canContinue($state))->toBeFalse();
});

it('execution time limit uses clock deterministically', function () {
    $state = new ToolUseState();
    $start = $state->startedAt();
    $clock = new FrozenClock($start->modify('+61 seconds'));
    $limit = new ExecutionTimeLimit(60, static fn(ToolUseState $state) => $state->startedAt(), $clock);
    expect($limit->canContinue($state))->toBeFalse();
});

it('tool call presence check reflects current step calls', function () {
    $state = new ToolUseState();
    $check = new ToolCallPresenceCheck(static fn(ToolUseState $state): bool => $state->currentStep()?->hasToolCalls() ?? false);
    expect($check->canContinue($state))->toBeFalse();

    $state = $state->withCurrentStep(new ToolUseStep());
    expect($check->canContinue($state))->toBeFalse();

    $calls = new ToolCalls(new ToolCall('a', []));
    $state = $state->withCurrentStep(new ToolUseStep(toolCalls: $calls));
    expect($check->canContinue($state))->toBeTrue();
});

it('error presence check stops on failures', function () {
    $state = new ToolUseState();
    $check = new ErrorPresenceCheck(static fn(ToolUseState $state): bool => $state->currentStep()?->hasErrors() ?? false);
    expect($check->canContinue($state))->toBeTrue();

    $execs = new ToolExecutions(
        new ToolExecution(new ToolCall('no', []), Result::failure(new \Exception('x')), new \DateTimeImmutable(), new \DateTimeImmutable())
    );
    $state = $state->withCurrentStep(new ToolUseStep(toolExecutions: $execs));
    expect($check->canContinue($state))->toBeFalse();
});

it('tool use continuation criteria reports emptiness', function () {
    $criteria = new ContinuationCriteria();
    expect($criteria->isEmpty())->toBeTrue();
});

it('tool use continuation criteria short circuits on failure', function () {
    $counter = new ToolUseCriterionCounter();
    $criteria = new ContinuationCriteria(
        new CountingToolUseCriterion($counter, false),
        new CountingToolUseCriterion($counter, true),
    );

    expect($criteria->canContinue(new ToolUseState()))->toBeFalse();
    expect($counter->calls)->toBe(1);
});

it('tool use continuation criteria withCriteria replaces set', function () {
    $counter = new ToolUseCriterionCounter();
    $criteria = new ContinuationCriteria(new CountingToolUseCriterion($counter, true));
    $replaced = $criteria->withCriteria(new CountingToolUseCriterion($counter, false));

    expect($criteria->canContinue(new ToolUseState()))->toBeTrue();
    expect($replaced->canContinue(new ToolUseState()))->toBeFalse();
});
