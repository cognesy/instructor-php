<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\PlanningSubagent\PlanningSubagentTool;
use Cognesy\Agents\Capability\PlanningSubagent\UsePlanningSubagent;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Tool\Tools\MockTool;

describe('Planning Subagent Capability', function () {
    it('appends planning instructions to system prompt on first execution', function () {
        $instructions = "Use planner before coding.\n\nSections:\n- Goal\n- Context\n- Acceptance Criteria";

        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('done')))
            ->withCapability(new UsePlanningSubagent(parentInstructions: $instructions))
            ->build();

        $state = AgentState::empty()->withSystemPrompt('Base system prompt');
        $result = $agent->execute($state);

        expect($result->context()->systemPrompt())->toContain('Base system prompt');
        expect($result->context()->systemPrompt())->toContain($instructions);
    });

    it('executes planning tool and returns markdown plan text', function () {
        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall(PlanningSubagentTool::TOOL_NAME, [
                'specification' => "Goal: Add planner\nContext: Existing agent\nExpected outcomes: Capability + tests\nAcceptance criteria: Works",
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final("## Plan\n1. Inspect architecture\n2. Implement capability\n3. Verify behavior"),
        ]);

        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver($driver))
            ->withCapability(new UsePlanningSubagent())
            ->build();

        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $execution = $next->lastStepToolExecutions()->all()[0];

        expect($execution->hasError())->toBeFalse();
        expect($execution->value())->toBeString();
        expect($execution->value())->toContain('## Plan');
    });

    it('returns error text when specification is empty', function () {
        $tool = new PlanningSubagentTool(
            parentTools: new Tools(),
            parentDriver: new FakeAgentDriver(),
            plannerSystemPrompt: 'Planner prompt',
            plannerBudget: ExecutionBudget::unlimited(),
        );

        $result = $tool->__invoke(specification: ' ');

        expect($result)->toBe('Error: specification is required');
    });

    it('filters recursive tools from planner toolset even when allowed', function () {
        $spawnTool = MockTool::returning('spawn_subagent', 'Spawn subagent', 'spawn');
        $plannerTool = MockTool::returning(PlanningSubagentTool::TOOL_NAME, 'Planner tool', 'plan');
        $readTool = MockTool::returning('read_file', 'Read file', 'content');

        $tools = new Tools($spawnTool, $plannerTool, $readTool);
        $allowList = new NameList('spawn_subagent', PlanningSubagentTool::TOOL_NAME, 'read_file');

        $tool = new PlanningSubagentTool(
            parentTools: $tools,
            parentDriver: new FakeAgentDriver(),
            plannerSystemPrompt: 'Planner prompt',
            plannerTools: $allowList,
            plannerBudget: ExecutionBudget::unlimited(),
        );

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('filterPlannerTools');

        /** @var Tools $filtered */
        $filtered = $method->invoke($tool, $tools, $allowList);

        expect($filtered->has('read_file'))->toBeTrue();
        expect($filtered->has('spawn_subagent'))->toBeFalse();
        expect($filtered->has(PlanningSubagentTool::TOOL_NAME))->toBeFalse();
    });
});
