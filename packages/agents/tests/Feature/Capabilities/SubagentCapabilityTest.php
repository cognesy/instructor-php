<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\SpawnSubagentTool;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\SubagentPolicy;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Events\SubagentCompleted;
use Cognesy\Agents\Events\SubagentSpawning;
use Cognesy\Agents\Tests\Support\FakeSubagentProvider;
use Cognesy\Messages\Messages;

beforeEach(function () {
    SpawnSubagentTool::clearSubagentStates();
});

describe('Subagent Capability', function () {
    it('executes spawn_subagent deterministically with missing spec', function () {
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
        expect($executions[0]->value())->toContain("Agent 'reviewer' not found");
    });

    it('returns error when nesting depth is exceeded', function () {
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
            maxDepth: 3,
        );

        $result = $tool->__invoke(subagent: 'reviewer', prompt: 'Review this');

        expect($result)->toContain('Maximum nesting depth (3) reached');
    });

    it('allows spawning within depth limit', function () {
        $spec = new AgentDefinition(
            name: 'reviewer',
            description: 'Reviews code',
            systemPrompt: 'You are a reviewer',
            model: 'inherit',
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
        expect($executions[0]->value())->toContain('[Subagent: reviewer]');
        expect($executions[0]->value())->toContain('I found 3 issues');
    });

    it('does not pollute parent messages with subagent internal history', function () {
        $spec = new AgentDefinition(
            name: 'reviewer',
            description: 'Reviews code',
            systemPrompt: 'You are a reviewer agent with special instructions',
            model: 'inherit',
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

        // Tool execution result contains subagent response
        $executions = $next->lastStepToolExecutions()->all();
        expect($executions)->toHaveCount(1);
        expect($executions[0]->value())->toContain('[Subagent: reviewer]');
        expect($executions[0]->value())->toContain('I found 3 issues');

        // Parent messages do NOT contain subagent's system prompt
        $allMessages = $next->messages()->toString();
        expect($allMessages)->not->toContain('You are a reviewer agent with special instructions');

        // Parent messages do NOT contain subagent's internal output
        $allParentMessages = $next->context()->messagesForInference()->toString();
        expect($allParentMessages)->not->toContain('You are a reviewer agent with special instructions');
    });

    it('formats successful subagent response', function () {
        $spec = new AgentDefinition(
            name: 'analyzer',
            description: 'Analyzes data',
            systemPrompt: 'You analyze data',
            model: 'inherit',
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
        expect($result)->toBe('[Subagent: analyzer] The data shows a 15% increase');
    });

    it('returns no response when subagent produces empty output', function () {
        $spec = new AgentDefinition(
            name: 'silent',
            description: 'A quiet agent',
            systemPrompt: 'Say nothing',
            model: 'inherit',
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
        expect($result)->toBe('[Subagent: silent] No response');
    });

    it('truncates response exceeding summaryMaxChars', function () {
        $spec = new AgentDefinition(
            name: 'verbose',
            description: 'A verbose agent',
            systemPrompt: 'Be verbose',
            model: 'inherit',
        );
        $provider = new FakeSubagentProvider($spec);

        $longResponse = str_repeat('x', 100);

        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall('spawn_subagent', [
                'subagent' => 'verbose',
                'prompt' => 'Write a lot',
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final($longResponse),
        ]);

        $agent = AgentBuilder::base()
            ->withDriver($driver)
            ->withCapability(new UseSubagents(
                provider: $provider,
                policy: new SubagentPolicy(summaryMaxChars: 50),
            ))
            ->build();

        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $result = $next->lastStepToolExecutions()->all()[0]->value();
        // Response should be truncated: "[Subagent: verbose] " + 50 chars + "\n..."
        expect($result)->toContain('[Subagent: verbose]');
        expect($result)->toEndWith("\n...");
        // The actual content after prefix should be at most 50 chars + "\n..."
        $content = str_replace('[Subagent: verbose] ', '', $result);
        $truncatedPart = str_replace("\n...", '', $content);
        expect(strlen($truncatedPart))->toBe(50);
    });

    it('stores subagent execution state for external access', function () {
        $spec = new AgentDefinition(
            name: 'worker',
            description: 'A worker agent',
            systemPrompt: 'You work',
            model: 'inherit',
        );
        $provider = new FakeSubagentProvider($spec);

        $driver = (new FakeAgentDriver([
            ScenarioStep::toolCall('spawn_subagent', [
                'subagent' => 'worker',
                'prompt' => 'Do work',
            ], executeTools: true),
        ]))->withChildSteps([
            ScenarioStep::final('Work done'),
        ]);

        $agent = AgentBuilder::base()
            ->withDriver($driver)
            ->withCapability(new UseSubagents(provider: $provider))
            ->build();

        foreach ($agent->iterate(AgentState::empty()) as $state) {
            break;
        }

        $states = SpawnSubagentTool::getSubagentStates();
        expect($states)->toHaveCount(1);
        expect($states[0]['name'])->toBe('worker');
        expect($states[0]['state'])->toBeInstanceOf(AgentState::class);

        SpawnSubagentTool::clearSubagentStates();
        expect(SpawnSubagentTool::getSubagentStates())->toHaveCount(0);
    });

    it('emits SubagentSpawning and SubagentCompleted events', function () {
        $spec = new AgentDefinition(
            name: 'emitter',
            description: 'An agent that emits events',
            systemPrompt: 'You emit events',
            model: 'inherit',
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
});
