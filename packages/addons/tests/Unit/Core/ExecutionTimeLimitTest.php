<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit as CoreExecutionTimeLimit;
use Cognesy\Addons\Tests\Support\FrozenClock;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

it('stops when execution time exceeds limit using a frozen clock', function () {
    $state = new ToolUseState();
    $startedAt = $state->startedAt();

    $limitSeconds = 30;
    $frozenNow = $startedAt->modify('+'.($limitSeconds + 1).' seconds');
    $clock = new FrozenClock($frozenNow);

    $limit = new CoreExecutionTimeLimit($limitSeconds, static fn(ToolUseState $state) => $state->startedAt(), $clock);
    // Over limit - forbid continuation
    expect($limit->decide($state))->toBe(ContinuationDecision::ForbidContinuation);
});

it('continues when within the time limit using a frozen clock', function () {
    $state = new ToolUseState();
    $startedAt = $state->startedAt();

    $limitSeconds = 30;
    $frozenNow = $startedAt->modify('+'.($limitSeconds - 1).' seconds');
    $clock = new FrozenClock($frozenNow);

    $limit = new CoreExecutionTimeLimit($limitSeconds, static fn(ToolUseState $state) => $state->startedAt(), $clock);
    // Under limit - allow continuation (guard permits)
    expect($limit->decide($state))->toBe(ContinuationDecision::AllowContinuation);
});
