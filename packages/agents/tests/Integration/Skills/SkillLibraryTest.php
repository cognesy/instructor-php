<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Integration\Skills;

use Cognesy\Agents\Capability\Skills\Skill;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Tests\Support\TestHelpers;


describe('SkillLibrary', function () {

    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/skill_library_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function () {
        TestHelpers::recursiveDelete($this->tempDir);
    });

    it('creates library with explicit path', function () {
        $library = new SkillLibrary($this->tempDir);

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

    it('parses skill without frontmatter using directory name', function () {
        $skillDir = $this->tempDir . '/simple-skill';
        mkdir($skillDir, 0755, true);
        file_put_contents($skillDir . '/SKILL.md', '# Simple Content');

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

    it('parses license field', function () {
        ($this->createSkillFileWithFrontmatter)('licensed', ['license' => 'MIT']);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('licensed');
        expect($skill->license)->toBe('MIT');
    });

    it('parses compatibility field', function () {
        ($this->createSkillFileWithFrontmatter)('compat', ['compatibility' => 'node >= 18']);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('compat');
        expect($skill->compatibility)->toBe('node >= 18');
    });

    it('parses metadata as key-value map', function () {
        ($this->createSkillFileWithFrontmatter)('meta', ['metadata' => ['author' => 'test', 'version' => '1.0']]);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('meta');
        expect($skill->metadata)->toBe(['author' => 'test', 'version' => 1.0]);
    });

    it('parses allowed-tools from space-delimited string', function () {
        ($this->createSkillFileWithFrontmatter)('tools-space', ['allowed-tools' => 'read_file write_file']);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('tools-space');
        expect($skill->allowedTools)->toBe(['read_file', 'write_file']);
    });

    it('parses allowed-tools from comma-delimited string', function () {
        ($this->createSkillFileWithFrontmatter)('tools-comma', ['allowed-tools' => 'read_file, write_file, edit_file']);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('tools-comma');
        expect($skill->allowedTools)->toBe(['read_file', 'write_file', 'edit_file']);
    });

    it('parses allowed-tools from YAML list', function () {
        $skillDir = $this->tempDir . '/tools-list';
        mkdir($skillDir, 0755, true);
        file_put_contents($skillDir . '/SKILL.md', "---\nname: tools-list\ndescription: YAML list tools\nallowed-tools:\n  - read_file\n  - write_file\n---\nContent");
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('tools-list');
        expect($skill->allowedTools)->toBe(['read_file', 'write_file']);
    });

    it('defaults standard fields to null or empty when absent', function () {
        ($this->createSkillFile)('minimal', 'Minimal skill');
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('minimal');
        expect($skill->license)->toBeNull();
        expect($skill->compatibility)->toBeNull();
        expect($skill->metadata)->toBe([]);
        expect($skill->allowedTools)->toBe([]);
    });

    it('parses disable-model-invocation field', function () {
        ($this->createSkillFileWithFrontmatter)('no-model', ['disable-model-invocation' => true]);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('no-model');
        expect($skill->disableModelInvocation)->toBeTrue();
    });

    it('parses user-invocable field', function () {
        ($this->createSkillFileWithFrontmatter)('background', ['user-invocable' => false]);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('background');
        expect($skill->userInvocable)->toBeFalse();
    });

    it('parses argument-hint field', function () {
        ($this->createSkillFileWithFrontmatter)('hinted', ['argument-hint' => '[file-path]']);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('hinted');
        expect($skill->argumentHint)->toBe('[file-path]');
    });

    it('parses model field', function () {
        ($this->createSkillFileWithFrontmatter)('modeled', ['model' => 'gpt-4o']);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('modeled');
        expect($skill->model)->toBe('gpt-4o');
    });

    it('parses context and agent fields', function () {
        ($this->createSkillFileWithFrontmatter)('forked', ['context' => 'fork', 'agent' => 'reviewer']);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('forked');
        expect($skill->context)->toBe('fork');
        expect($skill->agent)->toBe('reviewer');
    });

    it('ignores unknown frontmatter fields gracefully', function () {
        ($this->createSkillFileWithFrontmatter)('unknown-fields', ['unknown-key' => 'value', 'another' => 123]);
        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('unknown-fields');
        expect($skill)->toBeInstanceOf(Skill::class);
        expect($skill->name)->toBe('unknown-fields');
    });

    it('discovers examples folder as resources', function () {
        $skillDir = $this->tempDir . '/with-examples';
        mkdir($skillDir . '/examples', 0755, true);
        file_put_contents($skillDir . '/SKILL.md', "---\nname: with-examples\ndescription: Has examples\n---\nContent");
        file_put_contents($skillDir . '/examples/sample.md', '# Sample');

        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('with-examples');
        expect($skill->resources)->toContain('examples/sample.md');
    });

    it('excludes disabled skills from model-invocable list', function () {
        ($this->createSkillFileWithFrontmatter)('visible', ['description' => 'Visible']);
        ($this->createSkillFileWithFrontmatter)('hidden', ['description' => 'Hidden', 'disable-model-invocation' => true]);

        $library = new SkillLibrary($this->tempDir);
        $skills = $library->listSkills(modelInvocable: true);
        $names = array_column($skills, 'name');

        expect($names)->toContain('visible');
        expect($names)->not->toContain('hidden');
    });

    it('excludes non-user-invocable skills from user list', function () {
        ($this->createSkillFileWithFrontmatter)('user-skill', ['description' => 'For users']);
        ($this->createSkillFileWithFrontmatter)('bg-skill', ['description' => 'Background', 'user-invocable' => false]);

        $library = new SkillLibrary($this->tempDir);
        $skills = $library->listSkills(userInvocable: true);
        $names = array_column($skills, 'name');

        expect($names)->toContain('user-skill');
        expect($names)->not->toContain('bg-skill');
    });

    it('renders filtered skill list for model invocable', function () {
        ($this->createSkillFileWithFrontmatter)('ok-skill', ['description' => 'OK skill']);
        ($this->createSkillFileWithFrontmatter)('blocked-skill', ['description' => 'Blocked', 'disable-model-invocation' => true]);

        $library = new SkillLibrary($this->tempDir);
        $rendered = $library->renderSkillList(modelInvocable: true);

        expect($rendered)->toContain('ok-skill');
        expect($rendered)->not->toContain('blocked-skill');
    });

    it('includes argument hint in rendered skill list', function () {
        ($this->createSkillFileWithFrontmatter)('hint-skill', ['description' => 'Hinted', 'argument-hint' => '[file]']);

        $library = new SkillLibrary($this->tempDir);
        $rendered = $library->renderSkillList();

        expect($rendered)->toContain('[hint-skill [file]]: Hinted');
    });

    it('allows direct loading of disabled skills', function () {
        ($this->createSkillFileWithFrontmatter)('disabled', ['description' => 'Disabled skill', 'disable-model-invocation' => true]);

        $library = new SkillLibrary($this->tempDir);
        $skill = $library->getSkill('disabled');

        expect($skill)->toBeInstanceOf(Skill::class);
        expect($skill->disableModelInvocation)->toBeTrue();
    });

    // Helper to create skill files
    beforeEach(function () {
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

        $this->createSkillFileWithFrontmatter = function (string $name, array $extra, string $body = 'Content') {
            $skillDir = $this->tempDir . '/' . $name;
            mkdir($skillDir, 0755, true);
            $yaml = "---\nname: {$name}\n";
            foreach ($extra as $key => $value) {
                if (is_bool($value)) {
                    $yaml .= "{$key}: " . ($value ? 'true' : 'false') . "\n";
                } elseif (is_array($value)) {
                    $yaml .= "{$key}:\n";
                    foreach ($value as $k => $v) {
                        if (is_int($k)) {
                            $yaml .= "  - {$v}\n";
                        } else {
                            $yaml .= "  {$k}: {$v}\n";
                        }
                    }
                } else {
                    // Quote values that start with [ to avoid YAML list parsing
                    $stringVal = (string) $value;
                    if (str_starts_with($stringVal, '[')) {
                        $yaml .= "{$key}: \"{$stringVal}\"\n";
                    } else {
                        $yaml .= "{$key}: {$value}\n";
                    }
                }
            }
            $yaml .= "---\n{$body}";
            file_put_contents($skillDir . '/SKILL.md', $yaml);
        };
    });
});
