<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\HookStackObserver;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use DateTimeImmutable;

it('uses wall-clock execution time by default', function () {
    $agent = AgentBuilder::base()
        ->withTimeout(1)
        ->build();

    $observer = $agent->observer();
    expect($observer)->toBeInstanceOf(HookStackObserver::class);
    /** @var HookStackObserver $observer */
    $observer->hookStack()->executionStarted(new DateTimeImmutable('-5 seconds'));

    $state = AgentState::empty()->withNewStepExecution();
    $processed = $observer->hookStack()->process($state, HookType::BeforeStep);

    expect($processed->pendingStopSignal()?->reason)->toBe(StopReason::TimeLimitReached);
})->skip('hooks not integrated yet');
