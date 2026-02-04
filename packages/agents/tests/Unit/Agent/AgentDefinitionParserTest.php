<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;

describe('AgentDefinition::fromArray', function () {
    it('parses a full agent definition', function () {
        $data = [
            'name' => 'partner-assistant',
            'label' => 'Partner Assistant',
            'description' => 'Partner management helper',
            'blueprint' => 'basic',
            'systemPrompt' => "You are a Partner Management Assistant.",
            'tools' => ['tools', 'actions', 'invoke_action'],
            'toolsDeny' => ['bash'],
            'maxSteps' => 8,
            'maxTokens' => 50000,
            'timeoutSec' => 300,
            'capabilities' => ['tool_discovery', 'work_context'],
            'metadata' => ['cost_tier' => 'low'],
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->name)->toBe('partner-assistant');
        expect($definition->label())->toBe('Partner Assistant');
        expect($definition->description)->toBe('Partner management helper');
        expect($definition->systemPrompt)->toContain('Partner Management Assistant');
        expect($definition->blueprint)->toBe('basic');
        expect($definition->blueprintClass)->toBeNull();
        expect($definition->maxSteps)->toBe(8);
        expect($definition->maxTokens)->toBe(50000);
        expect($definition->timeoutSec)->toBe(300);
        expect($definition->tools->all())->toBe(['tools', 'actions', 'invoke_action']);
        expect($definition->toolsDeny->all())->toBe(['bash']);
        expect($definition->capabilities->all())->toBe(['tool_discovery', 'work_context']);
        expect($definition->metadata?->get('cost_tier'))->toBe('low');
    });

    it('parses a minimal agent definition', function () {
        $data = [
            'name' => 'minimal-agent',
            'description' => 'Minimal',
            'systemPrompt' => 'Run minimal mode.',
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->label())->toBe('minimal-agent');
        expect($definition->blueprint)->toBeNull();
        expect($definition->blueprintClass)->toBeNull();
        expect($definition->maxSteps)->toBeNull();
        expect($definition->tools)->not->toBeNull();
        expect($definition->tools?->isEmpty())->toBeTrue();
        expect($definition->capabilities->isEmpty())->toBeTrue();
        expect($definition->metadata?->isEmpty())->toBeTrue();
    });

    it('uses title as label when label is missing', function () {
        $data = [
            'name' => 'title-agent',
            'title' => 'Title Agent',
            'description' => 'Title-based label',
            'systemPrompt' => 'Something',
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->label())->toBe('Title Agent');
    });

    it('falls back to name for label when label and title are missing', function () {
        $data = [
            'name' => 'fallback-agent',
            'description' => 'Fallback label',
            'systemPrompt' => 'Fallback',
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->label())->toBe('fallback-agent');
    });

    it('filters invalid entries in name lists', function () {
        $data = [
            'name' => 'bad-tools',
            'description' => 'Agent with bad tools list',
            'systemPrompt' => 'You are an agent.',
            'tools' => ['read_file', 42, '', 'bash'],
        ];

        $definition = AgentDefinition::fromArray($data);

        expect($definition->tools->all())->toBe(['read_file', 'bash']);
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
});
