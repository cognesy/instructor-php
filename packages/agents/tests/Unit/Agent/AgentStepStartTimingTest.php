<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Messages\Messages;
use DateTimeImmutable;

it('records step timing data on completed steps', function () {
    $agent = AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('done')))
        ->build();

    $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

    $startedBefore = new DateTimeImmutable();
    $finalState = $agent->execute($state);
    $result = $finalState->lastStepExecution();

    expect($result)->not->toBeNull();

    $startedAt = $finalState->lastStepStartedAt();
    $completedAt = $finalState->lastStepCompletedAt();

    expect($startedAt)->not->toBeNull();
    expect($completedAt)->not->toBeNull();

    $startedBeforeFloat = (float) $startedBefore->format('U.u');
    $startedAtFloat = (float) $startedAt->format('U.u');
    $completedAtFloat = (float) $completedAt->format('U.u');

    expect($startedAtFloat)->toBeGreaterThanOrEqual($startedBeforeFloat)
        ->and($completedAtFloat)->toBeGreaterThanOrEqual($startedAtFloat);
});
