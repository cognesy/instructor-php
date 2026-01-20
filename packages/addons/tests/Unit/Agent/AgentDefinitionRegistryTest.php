<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinition;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionExecution;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionLlm;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionRegistry;
use Cognesy\Addons\AgentTemplate\Definitions\AgentDefinitionTools;
use Cognesy\Addons\Agent\Exceptions\AgentNotFoundException;
use Tests\Addons\Support\TestHelpers;

describe('AgentDefinitionRegistry', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_definition_registry_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->writeDefinition = function (
            string $dir,
            string $filename,
            string $id,
            string $name
        ): string {
            $yaml = <<<YAML
version: 1
id: {$id}
name: {$name}
description: {$name} description
system_prompt: {$name} prompt
llm:
  preset: anthropic
YAML;

            $path = $dir . '/' . $filename;
            file_put_contents($path, $yaml);
            return $path;
        };
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('registers and retrieves definitions', function () {
        $registry = new AgentDefinitionRegistry();
        $definition = new AgentDefinition(
            version: 1,
            id: 'agent-a',
            name: 'Agent A',
            description: 'Agent A description',
            systemPrompt: 'Agent A prompt',
            blueprint: null,
            blueprintClass: null,
            llm: new AgentDefinitionLlm('anthropic'),
            execution: new AgentDefinitionExecution(),
            tools: new AgentDefinitionTools(),
        );

        $registry->register($definition);

        expect($registry->get('agent-a'))->toBe($definition);
        expect($registry->names())->toBe(['agent-a']);
    });

    it('throws when definition is missing', function () {
        $registry = new AgentDefinitionRegistry();

        $get = fn() => $registry->get('missing');

        expect($get)->toThrow(AgentNotFoundException::class);
    });

    it('loads from paths with override precedence', function () {
        $lowDir = $this->tempDir . '/low';
        $highDir = $this->tempDir . '/high';
        mkdir($lowDir, 0755, true);
        mkdir($highDir, 0755, true);

        ($this->writeDefinition)($lowDir, 'agent.yaml', 'agent-a', 'Low Priority');
        ($this->writeDefinition)($highDir, 'agent.yaml', 'agent-a', 'High Priority');

        $registry = new AgentDefinitionRegistry();
        $result = $registry->loadFromPaths([$lowDir, $highDir]);

        expect($result->definitions['agent-a']->name)->toBe('High Priority');
        expect($registry->get('agent-a')->name)->toBe('High Priority');
    });

    it('records errors without discarding valid definitions', function () {
        ($this->writeDefinition)($this->tempDir, 'valid.yaml', 'valid', 'Valid');
        $invalidPath = $this->tempDir . '/invalid.yaml';
        file_put_contents($invalidPath, "version: 1\nid: invalid\n");

        $registry = new AgentDefinitionRegistry();
        $result = $registry->loadFromDirectory($this->tempDir);

        expect($result->definitions)->toHaveCount(1);
        expect($result->errors)->toHaveCount(1);
        expect($registry->errors())->toHaveCount(1);
        expect(array_key_exists($invalidPath, $registry->errors()))->toBeTrue();
    });

    it('auto-discovers definitions with correct precedence', function () {
        $projectPath = $this->tempDir . '/project';
        $projectAgents = $projectPath . '/.claude/agents';
        $packagePath = $this->tempDir . '/package';
        $userPath = $this->tempDir . '/user';
        mkdir($projectAgents, 0755, true);
        mkdir($packagePath, 0755, true);
        mkdir($userPath, 0755, true);

        ($this->writeDefinition)($userPath, 'agent.yaml', 'agent-a', 'User');
        ($this->writeDefinition)($packagePath, 'agent.yaml', 'agent-a', 'Package');
        ($this->writeDefinition)($projectAgents, 'agent.yaml', 'agent-a', 'Project');

        $registry = new AgentDefinitionRegistry();
        $result = $registry->autoDiscover($projectPath, $packagePath, $userPath);

        expect($result->definitions['agent-a']->name)->toBe('Project');
        expect($registry->get('agent-a')->name)->toBe('Project');
    });
});
