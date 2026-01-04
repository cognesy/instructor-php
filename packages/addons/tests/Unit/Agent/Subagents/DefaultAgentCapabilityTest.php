<?php declare(strict_types=1);

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Enums\AgentType;
use Cognesy\Addons\Agent\Subagents\DefaultAgentCapability;
use Cognesy\Addons\Agent\Tools\BashTool;
use Cognesy\Addons\Agent\Tools\EditFileTool;
use Cognesy\Addons\Agent\Tools\ReadFileTool;
use Cognesy\Addons\Agent\Tools\TodoWriteTool;
use Cognesy\Addons\Agent\Tools\WriteFileTool;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/capability_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->capability = new DefaultAgentCapability();
    $this->allTools = new Tools(
        new BashTool(baseDir: $this->tempDir),
        ReadFileTool::inDirectory($this->tempDir),
        WriteFileTool::inDirectory($this->tempDir),
        EditFileTool::inDirectory($this->tempDir),
        new TodoWriteTool(),
    );
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

// Explore agent type tests
it('allows bash and read_file tools for Explore type', function () {
    $filtered = $this->capability->toolsFor(AgentType::Explore, $this->allTools);
    $names = $filtered->names();

    expect($names)->toContain('bash');
    expect($names)->toContain('read_file');
});

it('does not allow write_file and edit_file tools for Explore type', function () {
    $filtered = $this->capability->toolsFor(AgentType::Explore, $this->allTools);
    $names = $filtered->names();

    expect($names)->not->toContain('write_file');
    expect($names)->not->toContain('edit_file');
});

it('does not allow todo_write tool for Explore type', function () {
    $filtered = $this->capability->toolsFor(AgentType::Explore, $this->allTools);
    $names = $filtered->names();

    expect($names)->not->toContain('todo_write');
});

it('validates Explore tool allowance correctly', function () {
    expect($this->capability->isToolAllowed(AgentType::Explore, 'bash'))->toBeTrue();
    expect($this->capability->isToolAllowed(AgentType::Explore, 'read_file'))->toBeTrue();
    expect($this->capability->isToolAllowed(AgentType::Explore, 'write_file'))->toBeFalse();
});

// Code agent type tests
it('allows all file and bash tools for Code type', function () {
    $filtered = $this->capability->toolsFor(AgentType::Code, $this->allTools);
    $names = $filtered->names();

    expect($names)->toContain('bash');
    expect($names)->toContain('read_file');
    expect($names)->toContain('write_file');
    expect($names)->toContain('edit_file');
    expect($names)->toContain('todo_write');
});

it('validates Code tool allowance correctly', function () {
    expect($this->capability->isToolAllowed(AgentType::Code, 'bash'))->toBeTrue();
    expect($this->capability->isToolAllowed(AgentType::Code, 'write_file'))->toBeTrue();
    expect($this->capability->isToolAllowed(AgentType::Code, 'todo_write'))->toBeTrue();
});

// Plan agent type tests
it('only allows read_file tool for Plan type', function () {
    $filtered = $this->capability->toolsFor(AgentType::Plan, $this->allTools);
    $names = $filtered->names();

    expect($names)->toBe(['read_file']);
});

it('does not allow execution tools for Plan type', function () {
    expect($this->capability->isToolAllowed(AgentType::Plan, 'bash'))->toBeFalse();
    expect($this->capability->isToolAllowed(AgentType::Plan, 'write_file'))->toBeFalse();
    expect($this->capability->isToolAllowed(AgentType::Plan, 'edit_file'))->toBeFalse();
});

// spawn_subagent blocking tests
it('always blocks spawn_subagent tool', function () {
    expect($this->capability->isToolAllowed(AgentType::Explore, 'spawn_subagent'))->toBeFalse();
    expect($this->capability->isToolAllowed(AgentType::Code, 'spawn_subagent'))->toBeFalse();
    expect($this->capability->isToolAllowed(AgentType::Plan, 'spawn_subagent'))->toBeFalse();
});

// System prompts tests
it('returns system prompt for explore type', function () {
    $prompt = $this->capability->systemPromptFor(AgentType::Explore);

    expect($prompt)->toContain('exploration');
});

it('returns system prompt for code type', function () {
    $prompt = $this->capability->systemPromptFor(AgentType::Code);

    expect($prompt)->toContain('coding');
});

it('returns system prompt for plan type', function () {
    $prompt = $this->capability->systemPromptFor(AgentType::Plan);

    expect($prompt)->toContain('planning');
});
