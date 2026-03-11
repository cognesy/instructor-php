<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Skills;

use Cognesy\Agents\Capability\Skills\Skill;

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

    it('has correct defaults for standard fields', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'body',
            path: '/path',
        );

        expect($skill->license)->toBeNull();
        expect($skill->compatibility)->toBeNull();
        expect($skill->metadata)->toBe([]);
        expect($skill->allowedTools)->toBe([]);
    });

    it('has correct defaults for extension fields', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'body',
            path: '/path',
        );

        expect($skill->disableModelInvocation)->toBeFalse();
        expect($skill->userInvocable)->toBeTrue();
        expect($skill->argumentHint)->toBeNull();
        expect($skill->model)->toBeNull();
        expect($skill->context)->toBeNull();
        expect($skill->agent)->toBeNull();
    });

    it('creates skill with all standard fields', function () {
        $skill = new Skill(
            name: 'full',
            description: 'Full skill',
            body: 'body',
            path: '/path',
            license: 'MIT',
            compatibility: 'node >= 18',
            metadata: ['author' => 'test'],
            allowedTools: ['read_file', 'write_file'],
        );

        expect($skill->license)->toBe('MIT');
        expect($skill->compatibility)->toBe('node >= 18');
        expect($skill->metadata)->toBe(['author' => 'test']);
        expect($skill->allowedTools)->toBe(['read_file', 'write_file']);
    });

    it('creates skill with extension fields', function () {
        $skill = new Skill(
            name: 'ext',
            description: 'Extension',
            body: 'body',
            path: '/path',
            disableModelInvocation: true,
            userInvocable: false,
            argumentHint: '[file]',
            model: 'gpt-4o',
            context: 'fork',
            agent: 'code-review-agent',
        );

        expect($skill->disableModelInvocation)->toBeTrue();
        expect($skill->userInvocable)->toBeFalse();
        expect($skill->argumentHint)->toBe('[file]');
        expect($skill->model)->toBe('gpt-4o');
        expect($skill->context)->toBe('fork');
        expect($skill->agent)->toBe('code-review-agent');
    });

    it('converts to array with only non-null fields', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'body',
            path: '/path',
        );

        $array = $skill->toArray();

        expect($array)->toHaveKeys(['name', 'description', 'body', 'path']);
        expect($array)->not->toHaveKey('license');
        expect($array)->not->toHaveKey('metadata');
        expect($array)->not->toHaveKey('allowed-tools');
        expect($array)->not->toHaveKey('model');
    });

    it('converts to array with all fields populated', function () {
        $skill = new Skill(
            name: 'full',
            description: 'desc',
            body: 'body',
            path: '/path',
            license: 'MIT',
            compatibility: 'any',
            metadata: ['k' => 'v'],
            allowedTools: ['tool1'],
            disableModelInvocation: true,
            userInvocable: false,
            argumentHint: '[arg]',
            model: 'gpt-4o',
            context: 'fork',
            agent: 'my-agent',
            resources: ['file.md'],
        );

        $array = $skill->toArray();

        expect($array['license'])->toBe('MIT');
        expect($array['allowed-tools'])->toBe(['tool1']);
        expect($array['disable-model-invocation'])->toBeTrue();
        expect($array['user-invocable'])->toBeFalse();
        expect($array['argument-hint'])->toBe('[arg]');
        expect($array['model'])->toBe('gpt-4o');
        expect($array['context'])->toBe('fork');
        expect($array['agent'])->toBe('my-agent');
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

    it('substitutes $ARGUMENTS with full argument string', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'Review $ARGUMENTS carefully',
            path: '/path',
        );

        $rendered = $skill->render('src/main.php');

        expect($rendered)->toContain('Review src/main.php carefully');
    });

    it('substitutes $ARGUMENTS[N] with positional args', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'File: $ARGUMENTS[0], Line: $ARGUMENTS[1]',
            path: '/path',
        );

        $rendered = $skill->render('main.php 42');

        expect($rendered)->toContain('File: main.php, Line: 42');
    });

    it('substitutes $N shorthand with positional args', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'Fix issue $0 in $1',
            path: '/path',
        );

        $rendered = $skill->render('123 src/app.php');

        expect($rendered)->toContain('Fix issue 123 in src/app.php');
    });

    it('appends arguments when no placeholder present', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'Do the review',
            path: '/path',
        );

        $rendered = $skill->render('src/main.php');

        expect($rendered)->toContain('Do the review');
        expect($rendered)->toContain('ARGUMENTS: src/main.php');
    });

    it('does not append when arguments are empty', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'Do the review',
            path: '/path',
        );

        $rendered = $skill->render('');

        expect($rendered)->not->toContain('ARGUMENTS:');
    });

    it('replaces missing positional args with empty string', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'A=$0 B=$1 C=$2',
            path: '/path',
        );

        $rendered = $skill->render('only-one');

        expect($rendered)->toContain('A=only-one B= C=');
    });

    it('does not substitute when no arguments provided', function () {
        $skill = new Skill(
            name: 'test',
            description: 'desc',
            body: 'Review $ARGUMENTS carefully',
            path: '/path',
        );

        $rendered = $skill->render();

        expect($rendered)->toContain('Review $ARGUMENTS carefully');
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

    it('renders metadata with argument hint', function () {
        $skill = new Skill(
            name: 'fix-issue',
            description: 'Fix a GitHub issue',
            body: 'Content',
            path: '/path',
            argumentHint: '[issue-number]',
        );

        $meta = $skill->renderMetadata();

        expect($meta)->toBe('[fix-issue [issue-number]]: Fix a GitHub issue');
    });
});
