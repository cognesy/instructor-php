<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Template;

use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\MockTool;

describe('DefinitionLoopFactory', function () {
    it('instantiates executable loop from definition capabilities', function () {
        $capabilities = new AgentCapabilityRegistry();
        $capabilities->register('driver.fake', new UseDriver(FakeAgentDriver::fromResponses('ok')));

        $definition = new AgentDefinition(
            name: 'basic-agent',
            description: 'Basic test agent',
            systemPrompt: 'You are helpful.',
            capabilities: NameList::fromArray(['driver.fake']),
        );

        $loop = (new DefinitionLoopFactory($capabilities))->instantiateAgentLoop($definition);
        $result = $loop->execute(AgentState::empty()->withUserMessage('hello'));

        expect(trim($result->finalResponse()->toString()))->toBe('ok');
    });

    it('resolves tools from tool registry and executes them', function () {
        $capabilities = new AgentCapabilityRegistry();
        $capabilities->register('driver.fake', new UseDriver(new FakeAgentDriver([
            ScenarioStep::toolCall('demo_tool', [], executeTools: true),
        ])));

        $tools = new ToolRegistry();
        $tools->register(MockTool::returning('demo_tool', 'Demo tool', 'done'));

        $definition = new AgentDefinition(
            name: 'tool-agent',
            description: 'Agent with tools',
            systemPrompt: 'Use tools.',
            capabilities: NameList::fromArray(['driver.fake']),
            tools: NameList::fromArray(['demo_tool']),
        );

        $loop = (new DefinitionLoopFactory($capabilities, $tools))->instantiateAgentLoop($definition);
        $next = null;
        foreach ($loop->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        $executions = $next?->lastStepToolExecutions()->all() ?? [];
        expect($executions)->toHaveCount(1)
            ->and($executions[0]->name())->toBe('demo_tool')
            ->and($executions[0]->hasError())->toBeFalse();
    });

    it('throws on unknown capability names', function () {
        $definition = new AgentDefinition(
            name: 'invalid-agent',
            description: 'Unknown capability',
            systemPrompt: 'Test.',
            capabilities: NameList::fromArray(['missing.capability']),
        );

        $factory = new DefinitionLoopFactory(new AgentCapabilityRegistry());

        expect(fn() => $factory->instantiateAgentLoop($definition))
            ->toThrow(\InvalidArgumentException::class);
    });
});
