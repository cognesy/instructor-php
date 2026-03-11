<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Skills;

use Cognesy\Agents\Capability\Skills\SkillModelOverrideHook;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

describe('SkillModelOverrideHook', function () {

    it('passes through when no model metadata set', function () {
        $hook = new SkillModelOverrideHook();
        $state = AgentState::empty();
        $context = HookContext::beforeStep($state);

        $result = $hook->handle($context);

        expect($result->state()->llmConfig())->toBeNull();
    });

    it('passes through when model metadata is empty string', function () {
        $hook = new SkillModelOverrideHook();
        $state = AgentState::empty()->withMetadata(SkillModelOverrideHook::META_KEY, '');
        $context = HookContext::beforeStep($state);

        $result = $hook->handle($context);

        expect($result->state()->llmConfig())->toBeNull();
    });

    it('creates LLMConfig when model is set and no existing config', function () {
        $hook = new SkillModelOverrideHook();
        $state = AgentState::empty()->withMetadata(SkillModelOverrideHook::META_KEY, 'gpt-4o');
        $context = HookContext::beforeStep($state);

        $result = $hook->handle($context);

        expect($result->state()->llmConfig())->not->toBeNull();
        expect($result->state()->llmConfig()->model)->toBe('gpt-4o');
    });

    it('overrides model on existing config preserving other fields', function () {
        $hook = new SkillModelOverrideHook();
        $existingConfig = new LLMConfig(
            apiUrl: 'https://api.example.com',
            apiKey: 'sk-test',
            model: 'gpt-3.5-turbo',
            maxTokens: 2048,
            driver: 'openai',
        );
        $state = AgentState::empty()
            ->withLLMConfig($existingConfig)
            ->withMetadata(SkillModelOverrideHook::META_KEY, 'gpt-4o');
        $context = HookContext::beforeStep($state);

        $result = $hook->handle($context);

        $config = $result->state()->llmConfig();
        expect($config->model)->toBe('gpt-4o');
        expect($config->apiUrl)->toBe('https://api.example.com');
        expect($config->apiKey)->toBe('sk-test');
        expect($config->maxTokens)->toBe(2048);
        expect($config->driver)->toBe('openai');
    });

    it('does not override when model already matches', function () {
        $hook = new SkillModelOverrideHook();
        $existingConfig = new LLMConfig(model: 'gpt-4o');
        $state = AgentState::empty()
            ->withLLMConfig($existingConfig)
            ->withMetadata(SkillModelOverrideHook::META_KEY, 'gpt-4o');
        $context = HookContext::beforeStep($state);

        $result = $hook->handle($context);

        // State should be unchanged (same reference)
        expect($result->state()->llmConfig()->model)->toBe('gpt-4o');
    });
});
