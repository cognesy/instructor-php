<?php declare(strict_types=1);

use Cognesy\Addons\Agent\Enums\AgentType;

describe('AgentType', function () {

    it('has explore type', function () {
        $type = AgentType::Explore;

        expect($type->value)->toBe('explore');
    });

    it('has code type', function () {
        $type = AgentType::Code;

        expect($type->value)->toBe('code');
    });

    it('has plan type', function () {
        $type = AgentType::Plan;

        expect($type->value)->toBe('plan');
    });

    it('provides description for explore', function () {
        expect(AgentType::Explore->description())->toContain('exploring');
        expect(AgentType::Explore->description())->toContain('Read-only');
    });

    it('provides description for code', function () {
        expect(AgentType::Code->description())->toContain('coding');
        expect(AgentType::Code->description())->toContain('file');
        expect(AgentType::Code->description())->toContain('bash');
    });

    it('provides description for plan', function () {
        expect(AgentType::Plan->description())->toContain('Planning');
        expect(AgentType::Plan->description())->toContain('Read-only');
    });

    it('provides system prompt addition for explore', function () {
        $prompt = AgentType::Explore->systemPromptAddition();

        expect($prompt)->toContain('exploration');
        expect($prompt)->toContain('Do not modify');
    });

    it('provides system prompt addition for code', function () {
        $prompt = AgentType::Code->systemPromptAddition();

        expect($prompt)->toContain('coding');
        expect($prompt)->toContain('read');
        expect($prompt)->toContain('write');
        expect($prompt)->toContain('execute');
    });

    it('provides system prompt addition for plan', function () {
        $prompt = AgentType::Plan->systemPromptAddition();

        expect($prompt)->toContain('planning');
        expect($prompt)->toContain('Do not execute');
    });

    it('can be created from string', function () {
        expect(AgentType::from('explore'))->toBe(AgentType::Explore);
        expect(AgentType::from('code'))->toBe(AgentType::Code);
        expect(AgentType::from('plan'))->toBe(AgentType::Plan);
    });
});
