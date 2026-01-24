<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Skills;

use Cognesy\Agents\AgentBuilder\Capabilities\Skills\Skill;

describe('Skill', function () {

    it('creates a skill with all properties', function () {
        $skill = new Skill(
            name: 'test-skill',
            description: 'A test skill',
            body: '# Test Skill\n\nThis is the content.',
            path: '/path/to/skill.md',
            resources: ['file1.php', 'file2.php'],
        );

        expect($skill->name)->toBe('test-skill');
        expect($skill->description)->toBe('A test skill');
        expect($skill->body)->toBe('# Test Skill\n\nThis is the content.');
        expect($skill->path)->toBe('/path/to/skill.md');
        expect($skill->resources)->toBe(['file1.php', 'file2.php']);
    });

    it('creates skill without resources', function () {
        $skill = new Skill(
            name: 'simple-skill',
            description: 'Simple',
            body: 'Content',
            path: '/path/skill.md',
        );

        expect($skill->resources)->toBe([]);
    });

    it('converts to array', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'body',
            path: '/path',
            resources: ['res.php'],
        );

        $array = $skill->toArray();

        expect($array)->toBe([
            'name' => 'test',
            'description' => 'desc',
            'body' => 'body',
            'path' => '/path',
            'resources' => ['res.php'],
        ]);
    });

    it('renders skill with tags', function () {
        $skill = new Skill(
            name: 'my-skill',
            description: 'Description',
            body: 'Skill content here',
            path: '/path',
        );

        $rendered = $skill->render();

        expect($rendered)->toContain('<skill name="my-skill">');
        expect($rendered)->toContain('Skill content here');
        expect($rendered)->toContain('</skill>');
    });

    it('renders skill with resources', function () {
        $skill = new Skill(
            name: 'my-skill',
            description: 'Description',
            body: 'Content',
            path: '/path',
            resources: ['helper.php', 'utils.php'],
        );

        $rendered = $skill->render();

        expect($rendered)->toContain('## Available Resources');
        expect($rendered)->toContain('- helper.php');
        expect($rendered)->toContain('- utils.php');
    });

    it('does not render resources section when empty', function () {
        $skill = new Skill(
            name: 'my-skill',
            description: 'Description',
            body: 'Content',
            path: '/path',
        );

        $rendered = $skill->render();

        expect($rendered)->not->toContain('## Available Resources');
    });

    it('renders metadata', function () {
        $skill = new Skill(
            name: 'code-review',
            description: 'Review code for best practices',
            body: 'Content',
            path: '/path',
        );

        $meta = $skill->renderMetadata();

        expect($meta)->toBe('[code-review]: Review code for best practices');
    });
});
