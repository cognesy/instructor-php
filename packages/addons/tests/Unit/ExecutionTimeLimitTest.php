<?php declare(strict_types=1);

use Cognesy\Addons\Tests\Support\FrozenClock;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ExecutionTimeLimit;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

it('stops when execution time exceeds limit using a frozen clock', function () {
    $state = new ToolUseState();
    $startedAt = $state->startedAt();

    $limitSeconds = 30;
    $frozenNow = $startedAt->modify('+'.($limitSeconds + 1).' seconds');
    $clock = new FrozenClock($frozenNow);

    $limit = new ExecutionTimeLimit($limitSeconds, $clock);
    expect($limit->canContinue($state))->toBeFalse();
});

it('continues when within the time limit using a frozen clock', function () {
    $state = new ToolUseState();
    $startedAt = $state->startedAt();

    $limitSeconds = 30;
    $frozenNow = $startedAt->modify('+'.($limitSeconds - 1).' seconds');
    $clock = new FrozenClock($frozenNow);

    $limit = new ExecutionTimeLimit($limitSeconds, $clock);
    expect($limit->canContinue($state))->toBeTrue();
});

