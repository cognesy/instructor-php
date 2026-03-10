<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Regression;

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Subagent\SpawnSubagentTool;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Template\AgentDefinitionLoader;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tests\Support\FakeSubagentProvider;
use Cognesy\Agents\Tests\Support\TestHelpers;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\FakeTool;

function makeTempDir(): string {
    $tempDir = sys_get_temp_dir() . '/agent_definition_regression_' . uniqid();
    mkdir($tempDir, 0755, true);
    return $tempDir;
}

it('keeps omitted tools and toolsDeny as null when loading definition files', function () {
    $tempDir = makeTempDir();
    $yaml = <<<'YAML'
name: reviewer
description: Reviews code
systemPrompt: You are a code reviewer.
YAML;
    $path = $tempDir . '/reviewer.yaml';
    file_put_contents($path, $yaml);

    try {
        $definition = (new AgentDefinitionLoader())->loadFile($path);

        expect($definition->tools)->toBeNull()
            ->and($definition->toolsDeny)->toBeNull()
            ->and($definition->inheritsAllTools())->toBeTrue();
    } finally {
        TestHelpers::recursiveDelete($tempDir);
    }
});

it('inherits all registry tools in DefinitionLoopFactory when tools are omitted', function () {
    $capabilities = new AgentCapabilityRegistry();
    $capabilities->register('driver.fake', new UseDriver(new FakeAgentDriver([
        ScenarioStep::toolCall('demo_tool', [], executeTools: true),
    ])));

    $tools = new ToolRegistry();
    $tools->register(FakeTool::returning('demo_tool', 'Demo tool', 'done'));

    $definition = AgentDefinition::fromArray([
        'name' => 'tool-agent',
        'description' => 'Agent with inherited tools',
        'systemPrompt' => 'Use tools.',
        'capabilities' => ['driver.fake'],
    ]);

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

it('inherits parent tools in SpawnSubagentTool when file-loaded definition omits tools', function () {
    $tempDir = makeTempDir();
    $yaml = <<<'YAML'
name: assistant
description: Helps with tasks
systemPrompt: You are helpful.
YAML;
    $path = $tempDir . '/assistant.yaml';
    file_put_contents($path, $yaml);

    try {
        $spec = (new AgentDefinitionLoader())->loadFile($path);
        $provider = new FakeSubagentProvider($spec);

        $toolA = FakeTool::returning('tool_a', 'Tool A', 'result a');
        $toolB = FakeTool::returning('tool_b', 'Tool B', 'result b');
        $parentTools = new Tools($toolA, $toolB);

        $tool = new SpawnSubagentTool(
            parentTools: $parentTools,
            parentDriver: new FakeAgentDriver(),
            provider: $provider,
            currentDepth: 0,
        );

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('filterTools');
        /** @var Tools $filtered */
        $filtered = $method->invoke($tool, $spec, $parentTools);

        expect($filtered->has('tool_a'))->toBeTrue()
            ->and($filtered->has('tool_b'))->toBeTrue();
    } finally {
        TestHelpers::recursiveDelete($tempDir);
    }
});

it('keeps explicit tool allowlist and denylist behavior unchanged', function () {
    $definition = AgentDefinition::fromArray([
        'name' => 'restricted',
        'description' => 'Restricted tools',
        'systemPrompt' => 'Restricted.',
        'tools' => ['tool_a'],
        'toolsDeny' => ['tool_b'],
    ]);

    expect($definition->tools)->not->toBeNull()
        ->and($definition->tools?->all())->toBe(['tool_a'])
        ->and($definition->toolsDeny)->not->toBeNull()
        ->and($definition->toolsDeny?->all())->toBe(['tool_b'])
        ->and($definition->inheritsAllTools())->toBeFalse();
});
