<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use InvalidArgumentException;

describe('AgentDefinition::fromArray', function () {
    it('parses a full agent definition', function () {
        $data = [
            'version' => 1,
            'id' => 'partner-assistant',
            'name' => 'Partner Assistant',
            'description' => 'Partner management helper',
            'blueprint' => 'basic',
            'system_prompt' => "You are a Partner Management Assistant.",
            'model' => 'anthropic',
            'tools' => ['tools', 'actions', 'invoke_action'],
            'tools_deny' => ['bash'],
            'max_steps' => 8,
            'max_tokens' => 50000,
            'timeout_sec' => 300,
            'capabilities' => ['tool_discovery', 'work_context'],
            'metadata' => ['cost_tier' => 'low'],
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->version)->toBe(1);
        expect($definition->id)->toBe('partner-assistant');
        expect($definition->name)->toBe('Partner Assistant');
        expect($definition->description)->toBe('Partner management helper');
        expect($definition->systemPrompt)->toContain('Partner Management Assistant');
        expect($definition->blueprint)->toBe('basic');
        expect($definition->blueprintClass)->toBeNull();
        expect($definition->model)->toBe('anthropic');
        expect($definition->maxSteps)->toBe(8);
        expect($definition->maxTokens)->toBe(50000);
        expect($definition->timeoutSec)->toBe(300);
        expect($definition->tools->all())->toBe(['tools', 'actions', 'invoke_action']);
        expect($definition->toolsDeny->all())->toBe(['bash']);
        expect($definition->capabilities->all())->toBe(['tool_discovery', 'work_context']);
        expect($definition->metadata)->toBe(['cost_tier' => 'low']);
    });

    it('parses a minimal agent definition', function () {
        $data = [
            'name' => 'Minimal Agent',
            'description' => 'Minimal',
            'system_prompt' => 'Run minimal mode.',
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->blueprint)->toBeNull();
        expect($definition->blueprintClass)->toBeNull();
        expect($definition->maxSteps)->toBeNull();
        expect($definition->tools)->toBeNull();
        expect($definition->capabilities->isEmpty())->toBeTrue();
        expect($definition->metadata)->toBe([]);
    });

    it('rejects missing required fields', function () {
        $data = [
            'name' => 'Missing Description',
            'system_prompt' => 'Something',
        ];

        $parse = fn() => AgentDefinition::fromArray($data);

        expect($parse)->toThrow(InvalidArgumentException::class);
    });

    it('rejects conflicting blueprint fields', function () {
        $data = [
            'name' => 'Conflict',
            'description' => 'Conflict',
            'system_prompt' => 'Conflict',
            'blueprint' => 'basic',
            'blueprint_class' => 'Acme\Agent\BasicAgent',
        ];

        $parse = fn() => AgentDefinition::fromArray($data);

        expect($parse)->toThrow(InvalidArgumentException::class);
    });

    it('supports nested execution fields', function () {
        $data = [
            'name' => 'nested-exec',
            'description' => 'Agent with nested execution',
            'system_prompt' => 'You are an agent.',
            'execution' => [
                'max_steps' => 10,
                'max_tokens' => 5000,
                'timeout_sec' => 60,
            ],
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->maxSteps)->toBe(10);
        expect($definition->maxTokens)->toBe(5000);
        expect($definition->timeoutSec)->toBe(60);
    });

    it('supports nested tools allow/deny format', function () {
        $data = [
            'name' => 'nested-tools',
            'description' => 'Agent with nested tools',
            'system_prompt' => 'You are an agent.',
            'tools' => [
                'allow' => ['read_file', 'write_file'],
                'deny' => ['bash'],
            ],
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->tools->all())->toBe(['read_file', 'write_file']);
        expect($definition->toolsDeny->all())->toBe(['bash']);
    });

    it('defaults id() to name when id is null', function () {
        $definition = new AgentDefinition(
            name: 'my-agent',
            description: 'Test',
            systemPrompt: 'Test prompt',
        );

        expect($definition->id())->toBe('my-agent');
    });

    it('returns explicit id when set', function () {
        $definition = new AgentDefinition(
            name: 'my-agent',
            description: 'Test',
            systemPrompt: 'Test prompt',
            id: 'custom-id',
        );

        expect($definition->id())->toBe('custom-id');
    });

    it('inheritsAllTools returns true when tools is null', function () {
        $definition = new AgentDefinition(
            name: 'my-agent',
            description: 'Test',
            systemPrompt: 'Test prompt',
        );

        expect($definition->inheritsAllTools())->toBeTrue();
    });

    it('hasSkills returns false when skills is null', function () {
        $definition = new AgentDefinition(
            name: 'my-agent',
            description: 'Test',
            systemPrompt: 'Test prompt',
        );

        expect($definition->hasSkills())->toBeFalse();
    });

    it('supports comma-separated tool strings', function () {
        $data = [
            'name' => 'comma-tools',
            'description' => 'Agent with comma tools',
            'system_prompt' => 'You are an agent.',
            'tools' => 'read_file, write_file, bash',
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->tools->all())->toBe(['read_file', 'write_file', 'bash']);
    });
});
