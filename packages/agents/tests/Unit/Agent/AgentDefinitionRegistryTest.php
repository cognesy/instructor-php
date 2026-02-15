<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Exceptions\AgentNotFoundException;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Tests\Support\TestHelpers;

describe('AgentDefinitionRegistry', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_definition_registry_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->writeYamlDefinition = function (
            string $dir,
            string $filename,
            string $name,
        ): string {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $yaml = <<<YAML
name: {$name}
label: {$name} label
description: {$name} description
systemPrompt: {$name} prompt
YAML;

            $path = $dir . '/' . $filename;
            file_put_contents($path, $yaml);
            return $path;
        };
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('registers and retrieves definitions by name', function () {
        $registry = new AgentDefinitionRegistry();
        $definition = new AgentDefinition(
            name: 'agent-a',
            description: 'Agent A description',
            systemPrompt: 'Agent A prompt',
        );

        $registry->register($definition);

        expect($registry->get('agent-a'))->toBe($definition);
        expect($registry->names())->toBe(['agent-a']);
        expect($registry->count())->toBe(1);
    });

    it('throws when definition is missing', function () {
        $registry = new AgentDefinitionRegistry();

        $get = fn() => $registry->get('missing');

        expect($get)->toThrow(AgentNotFoundException::class);
    });

    it('implements CanManageAgentDefinitions', function () {
        $registry = new AgentDefinitionRegistry();
        $a = new AgentDefinition(name: 'alpha', description: 'Alpha', systemPrompt: 'Alpha prompt');
        $b = new AgentDefinition(name: 'beta', description: 'Beta', systemPrompt: 'Beta prompt');

        $registry->registerMany($a, $b);

        expect($registry->count())->toBe(2);
        expect($registry->names())->toBe(['alpha', 'beta']);
        expect($registry->all())->toHaveCount(2);
        expect($registry->get('alpha')->description)->toBe('Alpha');
    });

    it('loads yaml definitions from directory', function () {
        ($this->writeYamlDefinition)($this->tempDir, 'agent.yaml', 'yaml-agent');

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($this->tempDir);

        expect($registry->count())->toBe(1);
        expect($registry->has('yaml-agent'))->toBeTrue();
    });

    it('loads from file with override on duplicate name', function () {
        $lowDir = $this->tempDir . '/low';
        $highDir = $this->tempDir . '/high';

        ($this->writeYamlDefinition)($lowDir, 'agent.yaml', 'shared-agent');
        ($this->writeYamlDefinition)($highDir, 'agent.yaml', 'shared-agent');

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($lowDir);
        $registry->loadFromDirectory($highDir);

        expect($registry->count())->toBe(1);
        expect($registry->has('shared-agent'))->toBeTrue();
    });

    it('loads definitions even when fields are missing', function () {
        ($this->writeYamlDefinition)($this->tempDir, 'valid.yaml', 'valid-agent');
        $invalidPath = $this->tempDir . '/invalid.yaml';
        file_put_contents($invalidPath, "not_a_valid: yaml\nmissing: required_fields\n");

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($this->tempDir);

        expect($registry->count())->toBe(2);
        expect($registry->has('valid-agent'))->toBeTrue();
        expect($registry->has(''))->toBeTrue();
        expect($registry->errors())->toBeEmpty();
    });

    it('auto-discovers definitions with correct precedence', function () {
        $projectPath = $this->tempDir . '/project';
        $projectAgents = $projectPath . '/.claude/agents';
        $packagePath = $this->tempDir . '/package';
        $userPath = $this->tempDir . '/user';

        ($this->writeYamlDefinition)($userPath, 'agent.yaml', 'shared-agent');
        ($this->writeYamlDefinition)($packagePath, 'agent.yaml', 'shared-agent');
        ($this->writeYamlDefinition)($projectAgents, 'agent.yaml', 'shared-agent');

        $registry = new AgentDefinitionRegistry();
        $registry->autoDiscover($projectPath, $packagePath, $userPath);

        // Project path is loaded last, so it wins
        expect($registry->count())->toBe(1);
        expect($registry->has('shared-agent'))->toBeTrue();
    });
});
