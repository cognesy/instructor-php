<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Tools\UseToolRegistry;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\MockTool;

describe('ToolRegistry Capability', function () {
    it('lists registry tools deterministically through the agent', function () {
        $registry = new ToolRegistry();
        $registry->register(MockTool::returning('demo_tool', 'Demo tool', 'ok'));

        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('tools', ['action' => 'list'], executeTools: true),
            ])))
            ->withCapability(new UseToolRegistry($registry))
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }
        $executions = $next->lastStepToolExecutions()->all();

        expect($executions)->toHaveCount(1);
        $result = $executions[0]->value();
        expect($result['success'] ?? null)->toBeTrue();
        expect(array_column($result['tools'] ?? [], 'name'))->toContain('demo_tool');
    });
});
