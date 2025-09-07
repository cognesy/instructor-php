<?php declare(strict_types=1);

use Cognesy\Addons\Tests\Support\FrozenClock;
use Cognesy\Addons\ToolUse\ContinuationCriteria\{ErrorPresenceCheck,
    ExecutionTimeLimit,
    StepsLimit,
    TokenUsageLimit,
    ToolCallPresenceCheck};
use Cognesy\Addons\ToolUse\Data\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Result\Result;

it('steps limit boundary works', function () {
    $state = new ToolUseState();
    $limit = new StepsLimit(1);
    expect($limit->canContinue($state))->toBeTrue();

    $state->addStep(new ToolUseStep());
    expect($limit->canContinue($state))->toBeFalse();
});

it('token usage limit boundary works', function () {
    $state = new ToolUseState();
    $state->accumulateUsage(new Usage(10, 0));
    $limit = new TokenUsageLimit(10);
    expect($limit->canContinue($state))->toBeFalse();
});

it('execution time limit uses clock deterministically', function () {
    $state = new ToolUseState();
    $start = $state->startedAt();
    $clock = new FrozenClock($start->modify('+61 seconds'));
    $limit = new ExecutionTimeLimit(60, $clock);
    expect($limit->canContinue($state))->toBeFalse();
});

it('tool call presence check reflects current step calls', function () {
    $state = new ToolUseState();
    $check = new ToolCallPresenceCheck();
    expect($check->canContinue($state))->toBeFalse();

    $calls = new ToolCalls([ new ToolCall('a', []) ]);
    $state->setCurrentStep(new ToolUseStep(toolCalls: $calls));
    expect($check->canContinue($state))->toBeTrue();
});

it('error presence check stops on failures', function () {
    $state = new ToolUseState();
    $check = new ErrorPresenceCheck();
    expect($check->canContinue($state))->toBeTrue();

    $execs = new ToolExecutions([
        new ToolExecution(new ToolCall('no', []), Result::failure(new Exception('x')), new DateTimeImmutable(), new DateTimeImmutable())
    ]);
    $state->setCurrentStep(new ToolUseStep(toolExecutions: $execs));
    expect($check->canContinue($state))->toBeFalse();
});

