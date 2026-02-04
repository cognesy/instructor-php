<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinitionLoader;
use Cognesy\Agents\Tests\Support\TestHelpers;
use RuntimeException;

describe('AgentDefinitionLoader', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_definition_loader_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('loads a YAML file', function () {
        $yaml = <<<'YAML'
name: agent-a
description: Agent A description
systemPrompt: Agent A prompt
label: Agent A
YAML;
        $path = $this->tempDir . '/agent.yaml';
        file_put_contents($path, $yaml);

        $definition = (new AgentDefinitionLoader())->loadFile($path);

        expect($definition)->toBeInstanceOf(AgentDefinition::class);
        expect($definition->name)->toBe('agent-a');
        expect($definition->description)->toBe('Agent A description');
    });

    it('loads a YML file', function () {
        $yaml = <<<'YAML'
name: agent-b
description: Agent B description
systemPrompt: Agent B prompt
YAML;
        $path = $this->tempDir . '/agent.yml';
        file_put_contents($path, $yaml);

        $definition = (new AgentDefinitionLoader())->loadFile($path);

        expect($definition->name)->toBe('agent-b');
    });

    it('loads a markdown file with YAML frontmatter', function () {
        $md = "---\nname: md-agent\nlabel: Markdown Agent\ndescription: Markdown agent\n---\nYou are a markdown agent.\n";
        $path = $this->tempDir . '/agent.md';
        file_put_contents($path, $md);

        $definition = (new AgentDefinitionLoader())->loadFile($path);

        expect($definition->name)->toBe('md-agent');
        expect($definition->label())->toBe('Markdown Agent');
        expect($definition->systemPrompt)->toBe('You are a markdown agent.');
    });

    it('throws for unsupported file extension', function () {
        $path = $this->tempDir . '/agent.txt';
        file_put_contents($path, 'not a valid agent');

        $load = fn() => (new AgentDefinitionLoader())->loadFile($path);

        expect($load)->toThrow(\InvalidArgumentException::class);
    });

    it('throws for missing file', function () {
        $load = fn() => (new AgentDefinitionLoader())->loadFile($this->tempDir . '/nonexistent.yaml');

        expect($load)->toThrow(RuntimeException::class);
    });

    it('accepts YAML with missing fields', function () {
        $path = $this->tempDir . '/bad.yaml';
        file_put_contents($path, "missing: required_fields\n");

        $definition = (new AgentDefinitionLoader())->loadFile($path);

        expect($definition->name)->toBe('');
        expect($definition->description)->toBe('');
        expect($definition->systemPrompt)->toBe('');
    });

    it('throws for markdown without frontmatter', function () {
        $path = $this->tempDir . '/bad.md';
        file_put_contents($path, 'no frontmatter here');

        $load = fn() => (new AgentDefinitionLoader())->loadFile($path);

        expect($load)->toThrow(\InvalidArgumentException::class);
    });

    it('handles Windows line endings in markdown', function () {
        $content = "---\r\nname: win-agent\r\nlabel: Windows Agent\r\ndescription: Win agent\r\n---\r\nWindows prompt.\r\n";
        $path = $this->tempDir . '/win.md';
        file_put_contents($path, $content);

        $definition = (new AgentDefinitionLoader())->loadFile($path);

        expect($definition->name)->toBe('win-agent');
        expect($definition->label())->toBe('Windows Agent');
        expect($definition->systemPrompt)->toBe('Windows prompt.');
    });
});
