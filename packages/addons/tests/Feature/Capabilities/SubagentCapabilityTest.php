<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;

describe('Subagent Capability', function () {
    it('executes spawn_subagent deterministically with missing spec', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::toolCall('spawn_subagent', [
                    'subagent' => 'reviewer',
                    'prompt' => 'Review this',
                ], executeTools: true),
            ]))
            ->withCapability(new UseSubagents())
            ->build();

        $next = $agent->nextStep(AgentState::empty());
        $executions = $next->currentStep()?->toolExecutions()->all() ?? [];

        expect($executions)->toHaveCount(1);
        expect($executions[0]->value())->toContain("Agent 'reviewer' not found");
    });
});
