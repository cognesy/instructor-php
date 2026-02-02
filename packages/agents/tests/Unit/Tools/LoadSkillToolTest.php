<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Tools;

use Cognesy\Agents\AgentBuilder\Capabilities\Skills\LoadSkillTool;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\SkillLibrary;
use Cognesy\Agents\Tests\Support\TestHelpers;

describe('LoadSkillTool', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/load_skill_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->createSkillFile = function (string $name, string $description, string $body = 'Content') {
            $skillDir = $this->tempDir . '/' . $name;
            mkdir($skillDir, 0755, true);
            $content = <<<SKILL
---
name: {$name}
description: {$description}
---
{$body}
SKILL;
            file_put_contents($skillDir . '/SKILL.md', $content);
        };
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('has correct name and description', function () {
        $tool = new LoadSkillTool();

        expect($tool->name())->toBe('load_skill');
        expect($tool->description())->toContain('Load a skill');
    });

    it('lists skills when list_skills is true', function () {
        ($this->createSkillFile)('skill1', 'First skill');
        ($this->createSkillFile)('skill2', 'Second skill');

        $library = new SkillLibrary($this->tempDir);
        $tool = LoadSkillTool::withLibrary($library);

        $result = $tool(list_skills: true);

        expect($result)->toContain('Available skills:');
        expect($result)->toContain('skill1');
        expect($result)->toContain('skill2');
    });

    it('lists skills when skill_name is null', function () {
        ($this->createSkillFile)('my-skill', 'A skill');

        $library = new SkillLibrary($this->tempDir);
        $tool = LoadSkillTool::withLibrary($library);

        $result = $tool(skill_name: null);

        expect($result)->toContain('Available skills:');
    });

    it('loads skill by name', function () {
        ($this->createSkillFile)('code-review', 'Review code', '# Code Review Guide\n\nReview all code.');

        $library = new SkillLibrary($this->tempDir);
        $tool = LoadSkillTool::withLibrary($library);

        $result = $tool(skill_name: 'code-review');

        expect($result)->toContain('<skill name="code-review">');
        expect($result)->toContain('# Code Review Guide');
        expect($result)->toContain('</skill>');
    });

    it('returns error for non-existent skill', function () {
        ($this->createSkillFile)('existing', 'Exists');

        $library = new SkillLibrary($this->tempDir);
        $tool = LoadSkillTool::withLibrary($library);

        $result = $tool(skill_name: 'nonexistent');

        expect($result)->toContain("Skill 'nonexistent' not found");
        expect($result)->toContain('Available skills:');
    });

    it('creates tool with library factory', function () {
        $library = new SkillLibrary($this->tempDir);
        $tool = LoadSkillTool::withLibrary($library);

        expect($tool)->toBeInstanceOf(LoadSkillTool::class);
    });

    it('generates valid tool schema', function () {
        $tool = new LoadSkillTool();
        $schema = $tool->toToolSchema();

        expect($schema['type'])->toBe('function');
        expect($schema['function']['name'])->toBe('load_skill');
        expect($schema['function']['parameters'])->toBeArray();
    });
});
