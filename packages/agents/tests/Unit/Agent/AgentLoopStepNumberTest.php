<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Messages\Messages;

it('increments step numbers across iterations', function () {
    $agent = AgentBuilder::base()
        ->withCapability(new UseDriver(new FakeAgentDriver([
            ScenarioStep::toolCall('lookup', ['q' => 'test'], executeTools: false),
            ScenarioStep::final('done'),
        ])))
        ->build();

    $state = AgentState::empty()->withMessages(Messages::fromString('ping'));
    $final = $agent->execute($state);

    expect($final->stepCount())->toBe(2);
    expect($final->stepExecutions()->count())->toBe(2);
});
