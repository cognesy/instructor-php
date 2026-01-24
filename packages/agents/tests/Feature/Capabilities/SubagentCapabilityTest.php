<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;

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
