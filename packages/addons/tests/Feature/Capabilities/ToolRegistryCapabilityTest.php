<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Tools\UseToolRegistry;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;
use Cognesy\Addons\Agent\Tools\ToolRegistry;
use Cognesy\Addons\Agent\Tools\Testing\MockTool;

describe('ToolRegistry Capability', function () {
    it('lists registry tools deterministically through the agent', function () {
        $registry = new ToolRegistry();
        $registry->register(MockTool::returning('demo_tool', 'Demo tool', 'ok'));

        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::toolCall('tools', ['action' => 'list'], executeTools: true),
            ]))
            ->withCapability(new UseToolRegistry($registry))
            ->build();

        $next = $agent->nextStep(AgentState::empty());
        $executions = $next->currentStep()?->toolExecutions()->all() ?? [];

        expect($executions)->toHaveCount(1);
        $result = $executions[0]->value();
        expect($result['success'] ?? null)->toBeTrue();
        expect(array_column($result['tools'] ?? [], 'name'))->toContain('demo_tool');
    });
});
