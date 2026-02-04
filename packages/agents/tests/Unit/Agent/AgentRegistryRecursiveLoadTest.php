<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentTemplate\Definitions\AgentDefinitionRegistry;
use Cognesy\Agents\Tests\Support\TestHelpers;

function writeAgentSpec(string $dir, string $filename, string $name): string {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $content = "---\nname: {$name}\nlabel: {$name} label\ndescription: {$name} description\n---\nYou are {$name}.\n";
    $path = $dir . '/' . $filename;
    file_put_contents($path, $content);
    return $path;
}

describe('AgentDefinitionRegistry::loadFromDirectory', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/agent_registry_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    // RECURSIVE LOADING ////////////////////////////////////////////

    it('finds files in nested subdirectories when recursive is true', function () {
        writeAgentSpec($this->tempDir, 'top.md', 'top-agent');
        writeAgentSpec($this->tempDir . '/sub', 'mid.md', 'mid-agent');
        writeAgentSpec($this->tempDir . '/sub/deep', 'deep.md', 'deep-agent');

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($this->tempDir, recursive: true);

        expect($registry->count())->toBe(3);
        expect($registry->has('top-agent'))->toBeTrue();
        expect($registry->has('mid-agent'))->toBeTrue();
        expect($registry->has('deep-agent'))->toBeTrue();
        expect($registry->errors())->toBeEmpty();
    });

    it('finds only top-level files when recursive is false', function () {
        writeAgentSpec($this->tempDir, 'top.md', 'top-agent');
        writeAgentSpec($this->tempDir . '/sub', 'mid.md', 'mid-agent');
        writeAgentSpec($this->tempDir . '/sub/deep', 'deep.md', 'deep-agent');

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($this->tempDir, recursive: false);

        expect($registry->count())->toBe(1);
        expect($registry->has('top-agent'))->toBeTrue();
        expect($registry->errors())->toBeEmpty();
    });

    it('returns empty result for a missing directory', function () {
        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($this->tempDir . '/nonexistent', recursive: true);

        expect($registry->count())->toBe(0);
        expect($registry->errors())->toBeEmpty();
    });

    it('ignores non-agent files when recursing', function () {
        writeAgentSpec($this->tempDir, 'agent.md', 'real-agent');
        file_put_contents($this->tempDir . '/readme.txt', 'not an agent');
        mkdir($this->tempDir . '/sub', 0755, true);
        file_put_contents($this->tempDir . '/sub/notes.json', 'also not an agent');

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($this->tempDir, recursive: true);

        expect($registry->count())->toBe(1);
        expect($registry->has('real-agent'))->toBeTrue();
    });

    // WINDOWS LINE ENDINGS /////////////////////////////////////////

    it('parses markdown files with Windows line endings', function () {
        $content = "---\r\nname: win-agent\r\ndescription: Windows agent\r\n---\r\nYou are a Windows agent.\r\n";
        $path = $this->tempDir . '/win.md';
        file_put_contents($path, $content);

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromFile($path);

        expect($registry->count())->toBe(1);
        expect($registry->has('win-agent'))->toBeTrue();
        expect($registry->errors())->toBeEmpty();
    });

    // YAML FILES ///////////////////////////////////////////////////

    it('loads yaml agent definitions', function () {
        $yaml = "name: yaml-agent\nlabel: YAML Agent\ndescription: YAML agent\nsystemPrompt: You are a YAML agent.\n";
        $path = $this->tempDir . '/agent.yaml';
        file_put_contents($path, $yaml);

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromFile($path);

        expect($registry->count())->toBe(1);
        expect($registry->has('yaml-agent'))->toBeTrue();
    });

    it('loads yml agent definitions', function () {
        $yaml = "name: yml-agent\nlabel: YML Agent\ndescription: YML agent\nsystemPrompt: You are a YML agent.\n";
        $path = $this->tempDir . '/agent.yml';
        file_put_contents($path, $yaml);

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromFile($path);

        expect($registry->count())->toBe(1);
        expect($registry->has('yml-agent'))->toBeTrue();
    });

    // STRUCTURED ERROR REPORTING ///////////////////////////////////

    it('records parse errors without discarding valid specs', function () {
        writeAgentSpec($this->tempDir, 'valid.md', 'valid-agent');
        $invalidPath = $this->tempDir . '/invalid.md';
        file_put_contents($invalidPath, "not valid frontmatter at all");

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($this->tempDir);

        expect($registry->count())->toBe(1);
        expect($registry->has('valid-agent'))->toBeTrue();
        expect($registry->errors())->toHaveCount(1);
        expect(array_key_exists($invalidPath, $registry->errors()))->toBeTrue();
    });

    it('accumulates errors across multiple loads', function () {
        $dir1 = $this->tempDir . '/a';
        $dir2 = $this->tempDir . '/b';
        mkdir($dir1, 0755, true);
        mkdir($dir2, 0755, true);

        writeAgentSpec($dir1, 'ok.md', 'ok-agent');
        file_put_contents($dir1 . '/bad1.md', 'broken');
        file_put_contents($dir2 . '/bad2.md', 'also broken');

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromDirectory($dir1);
        $registry->loadFromDirectory($dir2);

        expect($registry->count())->toBe(1);
        expect($registry->errors())->toHaveCount(2);
        expect(array_key_exists($dir1 . '/bad1.md', $registry->errors()))->toBeTrue();
        expect(array_key_exists($dir2 . '/bad2.md', $registry->errors()))->toBeTrue();
    });

    it('records errors from loadFromFile', function () {
        $path = $this->tempDir . '/broken.md';
        file_put_contents($path, 'no frontmatter');

        $registry = new AgentDefinitionRegistry();
        $registry->loadFromFile($path);

        expect($registry->count())->toBe(0);
        expect($registry->errors())->toHaveCount(1);
        expect(array_key_exists($path, $registry->errors()))->toBeTrue();
    });
});
