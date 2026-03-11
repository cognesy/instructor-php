<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Skills;

use Cognesy\Agents\Capability\Skills\Skill;
use Cognesy\Agents\Capability\Skills\SkillForkExecutor;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

describe('SkillForkExecutor', function () {

    it('executes a forked skill and returns the response', function () {
        $driver = FakeAgentDriver::fromResponses('Fork result: task completed');
        $executor = new SkillForkExecutor($driver, new Tools());

        $skill = new Skill(
            name: 'forked',
            description: 'A forked skill',
            body: 'Do the thing',
            path: '/path',
            context: 'fork',
        );

        $result = $executor->execute($skill);

        expect($result)->toContain('Fork result: task completed');
    });

    it('passes arguments through substitution', function () {
        $driver = FakeAgentDriver::fromResponses('Fixed issue 42');
        $executor = new SkillForkExecutor($driver, new Tools());

        $skill = new Skill(
            name: 'fix',
            description: 'Fix issue',
            body: 'Fix issue $0',
            path: '/path',
            context: 'fork',
        );

        $result = $executor->execute($skill, '42');

        expect($result)->toContain('Fixed issue 42');
    });

    it('executes with model override without error', function () {
        $driver = FakeAgentDriver::fromResponses('Done');
        $executor = new SkillForkExecutor($driver, new Tools());
        $parentConfig = new LLMConfig(
            apiUrl: 'https://api.test.com',
            apiKey: 'sk-test',
            model: 'gpt-3.5-turbo',
        );

        $skill = new Skill(
            name: 'model-skill',
            description: 'With model',
            body: 'Task',
            path: '/path',
            model: 'gpt-4o',
            context: 'fork',
        );

        // Should execute without throwing — FakeAgentDriver handles LLMConfig
        $result = $executor->execute($skill, null, $parentConfig);

        expect($result)->toBeString();
        expect($result)->not->toContain('failed');
    });

    it('returns inline content when no fork executor on LoadSkillTool', function () {
        $skill = new Skill(
            name: 'fork-no-executor',
            description: 'Fork without executor',
            body: 'Inline content',
            path: '/path',
            context: 'fork',
        );

        $rendered = $skill->render();
        expect($rendered)->toContain('Inline content');
    });
});
