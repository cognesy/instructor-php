<?php declare(strict_types=1);

use Cognesy\Agents\Data\AgentId;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ExecutionId;
use Cognesy\Agents\Data\ExecutionState;

it('round-trips typed ids through array boundary', function () {
    $state = AgentState::empty()->with(
        execution: ExecutionState::fresh(),
    );

    $serialized = $state->toArray();
    $restored = AgentState::fromArray($serialized);

    expect($serialized['agentId'])->toBeString()->not->toBeEmpty()
        ->and($restored->agentId())->toBeInstanceOf(AgentId::class)
        ->and($restored->agentId()->toString())->toBe($serialized['agentId'])
        ->and($restored->execution())->not->toBeNull()
        ->and($restored->execution()->executionId())->toBeInstanceOf(ExecutionId::class)
        ->and($restored->execution()->executionId()->toString())->toBe($serialized['execution']['executionId']);
});
