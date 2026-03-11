<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Skills;

use Cognesy\Agents\Capability\Skills\SkillToolFilterHook;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Messages\ToolCall;

describe('SkillToolFilterHook', function () {

    it('passes through when no allowed-tools metadata set', function () {
        $hook = new SkillToolFilterHook();
        $state = AgentState::empty();
        $toolCall = new ToolCall('some_tool', ['arg' => 'val']);
        $context = HookContext::beforeToolUse($state, $toolCall);

        $result = $hook->handle($context);

        expect($result->isToolExecutionBlocked())->toBeFalse();
    });

    it('passes through when allowed-tools is empty array', function () {
        $hook = new SkillToolFilterHook();
        $state = AgentState::empty()->withMetadata(SkillToolFilterHook::META_KEY, []);
        $toolCall = new ToolCall('some_tool', ['arg' => 'val']);
        $context = HookContext::beforeToolUse($state, $toolCall);

        $result = $hook->handle($context);

        expect($result->isToolExecutionBlocked())->toBeFalse();
    });

    it('allows tools in the allowed list', function () {
        $hook = new SkillToolFilterHook();
        $state = AgentState::empty()->withMetadata(SkillToolFilterHook::META_KEY, ['Read', 'Grep']);
        $toolCall = new ToolCall('Read', ['path' => '/file']);
        $context = HookContext::beforeToolUse($state, $toolCall);

        $result = $hook->handle($context);

        expect($result->isToolExecutionBlocked())->toBeFalse();
    });

    it('blocks tools not in the allowed list', function () {
        $hook = new SkillToolFilterHook();
        $state = AgentState::empty()->withMetadata(SkillToolFilterHook::META_KEY, ['Read', 'Grep']);
        $toolCall = new ToolCall('Write', ['path' => '/file', 'content' => 'x']);
        $context = HookContext::beforeToolUse($state, $toolCall);

        $result = $hook->handle($context);

        expect($result->isToolExecutionBlocked())->toBeTrue();
    });

    it('never blocks load_skill tool', function () {
        $hook = new SkillToolFilterHook();
        $state = AgentState::empty()->withMetadata(SkillToolFilterHook::META_KEY, ['Read']);
        $toolCall = new ToolCall('load_skill', ['skill_name' => 'other']);
        $context = HookContext::beforeToolUse($state, $toolCall);

        $result = $hook->handle($context);

        expect($result->isToolExecutionBlocked())->toBeFalse();
    });

    it('includes descriptive message when blocking', function () {
        $hook = new SkillToolFilterHook();
        $state = AgentState::empty()->withMetadata(SkillToolFilterHook::META_KEY, ['Read', 'Grep']);
        $toolCall = new ToolCall('Bash', ['command' => 'echo hello']);
        $context = HookContext::beforeToolUse($state, $toolCall);

        $result = $hook->handle($context);

        expect($result->isToolExecutionBlocked())->toBeTrue();
        $execution = $result->toolExecution();
        expect($execution)->not->toBeNull();
        expect($execution->errorAsString())->toContain('Bash');
        expect($execution->errorAsString())->toContain('Read, Grep');
    });
});
