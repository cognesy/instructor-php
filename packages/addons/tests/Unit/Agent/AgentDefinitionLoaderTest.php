<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Definitions\AgentDefinitionLoader;
use Tests\Addons\Support\TestHelpers;

describe('AgentDefinitionLoader', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_definition_loader_' . uniqid();
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

    it('loads a single YAML file into definitions map', function () {
        $file = ($this->writeDefinition)($this->tempDir, 'agent.yaml', 'agent-a', 'Agent A');

        $result = (new AgentDefinitionLoader())->loadFromFile($file);

        expect($result->errors)->toBe([]);
        expect($result->definitions)->toHaveCount(1);
        expect($result->definitions['agent-a']->name)->toBe('Agent A');
    });

    it('applies override precedence for paths', function () {
        $lowDir = $this->tempDir . '/low';
        $highDir = $this->tempDir . '/high';
        mkdir($lowDir, 0755, true);
        mkdir($highDir, 0755, true);

        ($this->writeDefinition)($lowDir, 'agent.yaml', 'agent-a', 'Low Priority');
        ($this->writeDefinition)($highDir, 'agent.yaml', 'agent-a', 'High Priority');

        $result = (new AgentDefinitionLoader())->loadFromPaths([$lowDir, $highDir]);

        expect($result->definitions['agent-a']->name)->toBe('High Priority');
    });

    it('skips invalid YAML files and reports errors', function () {
        ($this->writeDefinition)($this->tempDir, 'valid.yaml', 'valid', 'Valid');

        $invalidPath = $this->tempDir . '/invalid.yaml';
        file_put_contents($invalidPath, "version: 1\nid: invalid\n");

        $result = (new AgentDefinitionLoader())->loadFromDirectory($this->tempDir);

        expect($result->definitions)->toHaveCount(1);
        expect($result->errors)->toHaveCount(1);
        expect(array_key_exists($invalidPath, $result->errors))->toBeTrue();
    });
});
