<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Messages\Messages;

it('increments step numbers across iterations', function () {
    $agent = AgentBuilder::base()
        ->withDriver(new FakeAgentDriver([
            ScenarioStep::toolCall('lookup', ['q' => 'test'], executeTools: false),
            ScenarioStep::final('done'),
        ]))
        ->build();

    $state = AgentState::empty()->withMessages(Messages::fromString('ping'));
    $final = $agent->execute($state);

    expect($final->stepCount())->toBe(2);
    expect($final->stepExecutions()->count())->toBe(2);
});
