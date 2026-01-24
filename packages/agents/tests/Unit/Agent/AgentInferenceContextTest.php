<?php declare(strict_types=1);

use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Agent\ToolExecutor;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

it('compiles summary buffer and messages for inference in order', function () {
    $store = MessageStore::fromSections(
        new Section('summary', Messages::fromString('SUMMARY', 'system')),
        new Section('buffer', Messages::fromString('BUFFER', 'user')),
        new Section('messages', Messages::fromString('RECENT', 'user')),
    );
    $state = new AgentState(store: $store);

    $driver = new DeterministicAgentDriver([
        ScenarioStep::final('ok'),
    ]);

    $step = $driver->useTools($state, new Tools(), new ToolExecutor(new Tools()));

    expect(trim($step->inputMessages()->toString()))->toBe("SUMMARY\nBUFFER\nRECENT");
});
