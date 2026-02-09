<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions\SubagentDepthExceededException;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions\SubagentNotFoundException;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\SpawnSubagentTool;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\SubagentPolicy;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\Context\Compilers\SelectedSections;
use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\Budget;
use Cognesy\Agents\Core\Tools\MockTool;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Events\SubagentCompleted;
use Cognesy\Agents\Events\SubagentSpawning;
use Cognesy\Agents\Tests\Support\FakeSubagentProvider;
use Cognesy\Messages\Messages;

describe('Subagent Capability', function () {
    it('marks tool execution as failed when subagent spec not found', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('spawn_subagent', [
                    'subagent' => 'reviewer',
                    'prompt' => 'Review this',
                ], executeTools: true),
            ]))
            ->withCapability(new UseSubagents())
            ->build();

        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }
        $executions = $next->lastStepToolExecutions()->all();

        expect($executions)->toHaveCount(1);
        expect($executions[0]->hasError())->toBeTrue();
        expect($executions[0]->error())->toBeInstanceOf(SubagentNotFoundException::class);
        expect($executions[0]->error()->subagentName)->toBe('reviewer');
    });

    it('throws exception when nesting depth is exceeded', function () {
        $spec = new AgentDefinition(
            name: 'reviewer',
            description: 'Reviews code',
            systemPrompt: 'You are a reviewer',
        );
        $provider = new FakeSubagentProvider($spec);
        $driver = new FakeAgentDriver();

        $tool = new SpawnSubagentTool(
            parentTools: new \Cognesy\Agents\Core\Collections\Tools(),
            parentDriver: $driver,
            provider: $provider,
            currentDepth: 3,
            policy: new SubagentPolicy(maxDepth: 3),
        );

        expect(fn() => $tool->__invoke(subagent: 'reviewer', prompt: 'Review this'))
            ->toThrow(SubagentDepthExceededException::class);
    });

    it('allows spawning within depth limit', function () {
        $spec = new AgentDefinition(
            name: 'reviewer',
            description: 'Reviews code',
            systemPrompt: 'You are a reviewer',
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);

        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall('spawn_subagent', [
                'subagent' => 'reviewer',
                'prompt' => 'Review this code',
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final('I found 3 issues'),
        ]);

        $agent = AgentBuilder::base()
            ->withDriver($driver)
            ->withCapability(new UseSubagents(provider: $provider))
            ->build();

        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }
        $executions = $next->lastStepToolExecutions()->all();

        expect($executions)->toHaveCount(1);
        expect($executions[0]->value())->toBeInstanceOf(AgentState::class);
        expect($executions[0]->value()->finalResponse()->toString())->toContain('I found 3 issues');
    });

    it('does not pollute parent messages with subagent internal history', function () {
        $spec = new AgentDefinition(
            name: 'reviewer',
            description: 'Reviews code',
            systemPrompt: 'You are a reviewer agent with special instructions',
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);

        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall('spawn_subagent', [
                'subagent' => 'reviewer',
                'prompt' => 'Review this code',
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final('I found 3 issues'),
        ]);

        $agent = AgentBuilder::base()
            ->withDriver($driver)
            ->withCapability(new UseSubagents(provider: $provider))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString('Please review my code', 'user')
        );

        $next = null;
        foreach ($agent->iterate($state) as $stepState) {
            $next = $stepState;
            break;
        }

        // Tool execution result contains subagent AgentState
        $executions = $next->lastStepToolExecutions()->all();
        expect($executions)->toHaveCount(1);
        expect($executions[0]->value())->toBeInstanceOf(AgentState::class);
        expect($executions[0]->value()->finalResponse()->toString())->toContain('I found 3 issues');

        // Parent messages do NOT contain subagent's system prompt
        $allMessages = $next->messages()->toString();
        expect($allMessages)->not->toContain('You are a reviewer agent with special instructions');

        // Parent messages do NOT contain subagent's internal output
        $allParentMessages = (new SelectedSections([
            'summary',
            'buffer',
            'messages',
        ]))->compile($next)->toString();
        expect($allParentMessages)->not->toContain('You are a reviewer agent with special instructions');
    });

    it('formats successful subagent response', function () {
        $spec = new AgentDefinition(
            name: 'analyzer',
            description: 'Analyzes data',
            systemPrompt: 'You analyze data',
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);

        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall('spawn_subagent', [
                'subagent' => 'analyzer',
                'prompt' => 'Analyze this',
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final('The data shows a 15% increase'),
        ]);

        $agent = AgentBuilder::base()
            ->withDriver($driver)
            ->withCapability(new UseSubagents(provider: $provider))
            ->build();

        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $result = $next->lastStepToolExecutions()->all()[0]->value();
        expect($result)->toBeInstanceOf(AgentState::class);
        expect(trim($result->finalResponse()->toString()))->toBe('The data shows a 15% increase');
    });

    it('returns AgentState even when subagent produces empty output', function () {
        $spec = new AgentDefinition(
            name: 'silent',
            description: 'A quiet agent',
            systemPrompt: 'Say nothing',
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);

        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall('spawn_subagent', [
                'subagent' => 'silent',
                'prompt' => 'Do something',
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final(''),
        ]);

        $agent = AgentBuilder::base()
            ->withDriver($driver)
            ->withCapability(new UseSubagents(provider: $provider))
            ->build();

        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $result = $next->lastStepToolExecutions()->all()[0]->value();
        expect($result)->toBeInstanceOf(AgentState::class);
        expect($result->hasFinalResponse())->toBeFalse();
    });

    it('emits SubagentSpawning and SubagentCompleted events', function () {
        $spec = new AgentDefinition(
            name: 'emitter',
            description: 'An agent that emits events',
            systemPrompt: 'You emit events',
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);

        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall('spawn_subagent', [
                'subagent' => 'emitter',
                'prompt' => 'Do something',
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final('Done'),
        ]);

        $agent = AgentBuilder::base()
            ->withDriver($driver)
            ->withCapability(new UseSubagents(provider: $provider))
            ->build();

        $spawningEvents = [];
        $completedEvents = [];
        $agent->wiretap(function (object $event) use (&$spawningEvents, &$completedEvents): void {
            if ($event instanceof SubagentSpawning) {
                $spawningEvents[] = $event;
            }
            if ($event instanceof SubagentCompleted) {
                $completedEvents[] = $event;
            }
        });

        foreach ($agent->iterate(AgentState::empty()) as $state) {
            break;
        }

        expect($spawningEvents)->toHaveCount(1);
        expect($spawningEvents[0]->subagentName)->toBe('emitter');
        expect($spawningEvents[0]->depth)->toBe(0);
        expect($spawningEvents[0]->maxDepth)->toBe(3);

        expect($completedEvents)->toHaveCount(1);
        expect($completedEvents[0]->subagentName)->toBe('emitter');
        expect($completedEvents[0]->steps)->toBeGreaterThanOrEqual(0);
    });

    it('applies toolsDeny to filter out denied tools', function () {
        $tool1 = MockTool::returning('allowed_tool', 'An allowed tool', 'allowed result');
        $tool2 = MockTool::returning('denied_tool', 'A denied tool', 'denied result');

        $spec = new AgentDefinition(
            name: 'restricted',
            description: 'A restricted agent',
            systemPrompt: 'You are restricted',
            toolsDeny: new NameList('denied_tool'),
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);
        $driver = new FakeAgentDriver();

        $tool = new SpawnSubagentTool(
            parentTools: new Tools($tool1, $tool2),
            parentDriver: $driver,
            provider: $provider,
            currentDepth: 0,
        );

        // Access via reflection to verify tool filtering
        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('filterTools');


        $filteredTools = $method->invoke($tool, $spec, new Tools($tool1, $tool2));

        expect($filteredTools->has('allowed_tool'))->toBeTrue();
        expect($filteredTools->has('denied_tool'))->toBeFalse();
    });

    it('applies tools allowlist when specified', function () {
        $tool1 = MockTool::returning('allowed_tool', 'An allowed tool', 'allowed result');
        $tool2 = MockTool::returning('other_tool', 'Another tool', 'other result');

        $spec = new AgentDefinition(
            name: 'selective',
            description: 'A selective agent',
            systemPrompt: 'You are selective',
            tools: new NameList('allowed_tool'),
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);
        $driver = new FakeAgentDriver();

        $tool = new SpawnSubagentTool(
            parentTools: new Tools($tool1, $tool2),
            parentDriver: $driver,
            provider: $provider,
            currentDepth: 0,
        );

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('filterTools');


        $filteredTools = $method->invoke($tool, $spec, new Tools($tool1, $tool2));

        expect($filteredTools->has('allowed_tool'))->toBeTrue();
        expect($filteredTools->has('other_tool'))->toBeFalse();
    });

    it('combines tools allowlist and denylist correctly', function () {
        $tool1 = MockTool::returning('tool_a', 'Tool A', 'result a');
        $tool2 = MockTool::returning('tool_b', 'Tool B', 'result b');
        $tool3 = MockTool::returning('tool_c', 'Tool C', 'result c');

        // Allow tool_a and tool_b, but deny tool_b
        $spec = new AgentDefinition(
            name: 'combined',
            description: 'A combined filtering agent',
            systemPrompt: 'You use combined filtering',
            tools: new NameList('tool_a', 'tool_b'),
            toolsDeny: new NameList('tool_b'),
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);
        $driver = new FakeAgentDriver();

        $tool = new SpawnSubagentTool(
            parentTools: new Tools($tool1, $tool2, $tool3),
            parentDriver: $driver,
            provider: $provider,
            currentDepth: 0,
        );

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('filterTools');


        $filteredTools = $method->invoke($tool, $spec, new Tools($tool1, $tool2, $tool3));

        // tool_a: allowed and not denied -> included
        expect($filteredTools->has('tool_a'))->toBeTrue();
        // tool_b: allowed but denied -> excluded
        expect($filteredTools->has('tool_b'))->toBeFalse();
        // tool_c: not allowed -> excluded
        expect($filteredTools->has('tool_c'))->toBeFalse();
    });

    it('propagates parent budget to child and caps with definition limits', function () {
        $spec = new AgentDefinition(
            name: 'budgeted',
            description: 'A budgeted agent',
            systemPrompt: 'You have limits',
            maxSteps: 100,   // definition wants 100
            maxTokens: 5000, // definition wants 5000
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);
        $driver = new FakeAgentDriver();

        // Parent has remaining budget of only 10 steps and 1000 tokens
        $parentBudget = new Budget(maxSteps: 10, maxTokens: 1000);
        $parentState = AgentState::empty()->withBudget($parentBudget);

        $tool = new SpawnSubagentTool(
            parentTools: new Tools(),
            parentDriver: $driver,
            provider: $provider,
            currentDepth: 0,
        );
        $tool = $tool->withAgentState($parentState);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('computeEffectiveBudget');


        $effectiveBudget = $method->invoke($tool, $spec);

        // Should be capped at parent's remaining budget, not definition's larger limits
        expect($effectiveBudget->maxSteps)->toBe(10);   // min(100, 10) = 10
        expect($effectiveBudget->maxTokens)->toBe(1000); // min(5000, 1000) = 1000
    });

    it('uses definition limits when parent has unlimited budget', function () {
        $spec = new AgentDefinition(
            name: 'limited',
            description: 'A limited agent',
            systemPrompt: 'You have definition limits',
            maxSteps: 5,
            maxTokens: 500,
            timeoutSec: 30,
            llmConfig: '',
        );
        $provider = new FakeSubagentProvider($spec);
        $driver = new FakeAgentDriver();

        // Parent has unlimited budget
        $parentState = AgentState::empty()->withBudget(Budget::unlimited());

        $tool = new SpawnSubagentTool(
            parentTools: new Tools(),
            parentDriver: $driver,
            provider: $provider,
            currentDepth: 0,
        );
        $tool = $tool->withAgentState($parentState);

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('computeEffectiveBudget');


        $effectiveBudget = $method->invoke($tool, $spec);

        // Should use definition limits since parent is unlimited
        expect($effectiveBudget->maxSteps)->toBe(5);
        expect($effectiveBudget->maxTokens)->toBe(500);
        expect($effectiveBudget->maxSeconds)->toBe(30.0);
    });

    it('propagates budget correctly through multiple nesting levels', function () {
        // Simulate: root (100 steps) → level1 (50 steps used, 50 remaining) → level2 (wants 100, gets 50)

        // Level 1's definition - wants 80 steps
        $level1Spec = new AgentDefinition(
            name: 'level1',
            description: 'Level 1 agent',
            systemPrompt: 'You are level 1',
            maxSteps: 80,
            llmConfig: '',
        );

        // Level 2's definition - wants 100 steps
        $level2Spec = new AgentDefinition(
            name: 'level2',
            description: 'Level 2 agent',
            systemPrompt: 'You are level 2',
            maxSteps: 100,
            llmConfig: '',
        );

        $driver = new FakeAgentDriver();

        // Root has budget of 100 steps
        $rootBudget = new Budget(maxSteps: 100);

        // --- Level 1 spawning ---
        $rootState = AgentState::empty()->withBudget($rootBudget);

        $level1Tool = (new SpawnSubagentTool(
            parentTools: new Tools(),
            parentDriver: $driver,
            provider: new FakeSubagentProvider($level1Spec),
            currentDepth: 0,
        ))->withAgentState($rootState);

        $reflection = new \ReflectionClass($level1Tool);
        $method = $reflection->getMethod('computeEffectiveBudget');


        $level1Budget = $method->invoke($level1Tool, $level1Spec);

        // Level 1 gets min(80, 100) = 80 steps
        expect($level1Budget->maxSteps)->toBe(80);

        // --- Level 2 spawning (after level 1 used 30 steps) ---
        // Simulate level 1 having used some resources
        $level1Remaining = $level1Budget->remaining(stepsUsed: 30); // 80 - 30 = 50 remaining
        expect($level1Remaining->maxSteps)->toBe(50);

        $level1State = AgentState::empty()->withBudget($level1Remaining);

        $level2Tool = (new SpawnSubagentTool(
            parentTools: new Tools(),
            parentDriver: $driver,
            provider: new FakeSubagentProvider($level2Spec),
            currentDepth: 1,
        ))->withAgentState($level1State);

        $level2Budget = $method->invoke($level2Tool, $level2Spec);

        // Level 2 wants 100 but parent only has 50 remaining
        // Gets min(100, 50) = 50 steps
        expect($level2Budget->maxSteps)->toBe(50);
    });

    it('chains budget exhaustion check through nesting levels', function () {
        $budget = new Budget(maxSteps: 10, maxTokens: 1000);

        // After consuming most resources
        $remaining = $budget->remaining(stepsUsed: 8, tokensUsed: 900);
        expect($remaining->maxSteps)->toBe(2);
        expect($remaining->maxTokens)->toBe(100);
        expect($remaining->isExhausted())->toBeFalse();

        // After exhausting steps
        $exhausted = $budget->remaining(stepsUsed: 10, tokensUsed: 500);
        expect($exhausted->maxSteps)->toBe(0);
        expect($exhausted->isExhausted())->toBeTrue();

        // After exhausting tokens
        $exhaustedTokens = $budget->remaining(stepsUsed: 5, tokensUsed: 1000);
        expect($exhaustedTokens->maxTokens)->toBe(0);
        expect($exhaustedTokens->isExhausted())->toBeTrue();
    });
});
