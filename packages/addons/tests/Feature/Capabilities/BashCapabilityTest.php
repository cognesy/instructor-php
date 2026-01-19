<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;

describe('Bash Capability', function () {
    it('executes bash tool deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::toolCall('bash', ['command' => 'rm -rf /'], executeTools: true),
            ]))
            ->withCapability(new UseBash())
            ->build();

        $next = $agent->nextStep(AgentState::empty());

        $executions = $next->currentStep()?->toolExecutions()->all() ?? [];
        expect($executions)->toHaveCount(1);
        expect($executions[0]->name())->toBe('bash');
        expect($executions[0]->value())->toBe('Error: Command blocked by safety policy');
    });
});
