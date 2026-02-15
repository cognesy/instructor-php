<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

describe('Bash Capability', function () {
    it('executes bash tool deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('bash', ['command' => 'rm -rf /'], executeTools: true),
            ])))
            ->withCapability(new UseBash())
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $executions = $next->lastStepToolExecutions()->all();
        expect($executions)->toHaveCount(1);
        expect($executions[0]->name())->toBe('bash');
        expect($executions[0]->value())->toBe('Error: Command blocked by safety policy');
    });
});
