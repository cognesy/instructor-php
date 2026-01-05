<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Skills;

use Cognesy\Addons\Agent\Capabilities\Skills\Skill;
use Cognesy\Addons\Agent\Capabilities\Skills\SkillLibrary;
use Tests\Addons\Support\TestHelpers;


describe('SkillLibrary', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/skill_library_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('creates library with default path', function () {
        $library = new SkillLibrary();

        expect($library)->toBeInstanceOf(SkillLibrary::class);
    });

    it('creates library with custom path', function () {
        $library = SkillLibrary::inDirectory($this->tempDir);

        expect($library)->toBeInstanceOf(SkillLibrary::class);
    });

    it('lists skills from directory', function () {
        ($this->createSkillFile)('skill1', 'First skill');
        ($this->createSkillFile)('skill2', 'Second skill');

        $library = new SkillLibrary($this->tempDir);
        $skills = $library->listSkills();

        expect($skills)->toHaveCount(2);
        expect(array_column($skills, 'name'))->toContain('skill1');
        expect(array_column($skills, 'name'))->toContain('skill2');
    });

    it('returns empty list for empty directory', function () {
        $library = new SkillLibrary($this->tempDir);
        $skills = $library->listSkills();

        expect($skills)->toBeEmpty();
    });

    it('returns empty list for non-existent directory', function () {
        $library = new SkillLibrary($this->tempDir . '/nonexistent');
        $skills = $library->listSkills();

        expect($skills)->toBeEmpty();
    });

    it('checks if skill exists', function () {
        ($this->createSkillFile)('existing-skill', 'A skill');

        $library = new SkillLibrary($this->tempDir);

        expect($library->hasSkill('existing-skill'))->toBeTrue();
        expect($library->hasSkill('nonexistent'))->toBeFalse();
    });

    it('gets skill by name', function () {
        ($this->createSkillFile)('my-skill', 'My description', '# My Skill Content');

        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('my-skill');

        expect($skill)->toBeInstanceOf(Skill::class);
        expect($skill->name)->toBe('my-skill');
        expect($skill->description)->toBe('My description');
        expect($skill->body)->toContain('# My Skill Content');
    });

    it('returns null for non-existent skill', function () {
        $library = new SkillLibrary($this->tempDir);

        expect($library->getSkill('nonexistent'))->toBeNull();
    });

    it('caches loaded skills', function () {
        ($this->createSkillFile)('cached-skill', 'Description');

        $library = new SkillLibrary($this->tempDir);

        $skill1 = $library->getSkill('cached-skill');
        $skill2 = $library->getSkill('cached-skill');

        expect($skill1)->toBe($skill2);
    });

    it('renders skill list', function () {
        ($this->createSkillFile)('skill-a', 'Description A');
        ($this->createSkillFile)('skill-b', 'Description B');

        $library = new SkillLibrary($this->tempDir);
        $rendered = $library->renderSkillList();

        expect($rendered)->toContain('Available skills:');
        expect($rendered)->toContain('[skill-a]: Description A');
        expect($rendered)->toContain('[skill-b]: Description B');
    });

    it('renders no skills message when empty', function () {
        $library = new SkillLibrary($this->tempDir);
        $rendered = $library->renderSkillList();

        expect($rendered)->toBe('(no skills available)');
    });

    it('parses skill without frontmatter using filename as name', function () {
        file_put_contents($this->tempDir . '/simple-skill.md', '# Simple Content');

        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('simple-skill');

        expect($skill)->toBeInstanceOf(Skill::class);
        expect($skill->name)->toBe('simple-skill');
        expect($skill->body)->toBe('# Simple Content');
    });

    it('loads skills from SKILL.md folders', function () {
        $skillDir = $this->tempDir . '/folder-skill';
        mkdir($skillDir . '/scripts', 0755, true);
        file_put_contents($skillDir . '/SKILL.md', <<<SKILL
---
name: folder-skill
description: Folder skill description
---
# Folder Skill Content
SKILL);
        file_put_contents($skillDir . '/scripts/run.sh', '#!/bin/bash');

        $library = new SkillLibrary($this->tempDir);
        $skills = $library->listSkills();
        $skill = $library->getSkill('folder-skill');

        expect(array_column($skills, 'name'))->toContain('folder-skill');
        expect($skill)->toBeInstanceOf(Skill::class);
        expect($skill->body)->toContain('# Folder Skill Content');
        expect($skill->resources)->toContain('scripts/run.sh');
    });

    // Helper to create skill files
    beforeEach(function () {
        $this->createSkillFile = function (string $name, string $description, string $body = 'Content') {
            $content = <<<SKILL
---
name: {$name}
description: {$description}
---
{$body}
SKILL;
            file_put_contents($this->tempDir . '/' . $name . '.md', $content);
        };
    });
});
