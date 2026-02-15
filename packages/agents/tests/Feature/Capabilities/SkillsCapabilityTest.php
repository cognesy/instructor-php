<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Capability\Skills\UseSkills;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

describe('Skills Capability', function () {
    it('injects skills metadata deterministically through the agent', function () {
        $skillsDir = sys_get_temp_dir() . '/skills_capability_' . uniqid();
        $skillPath = $skillsDir . '/demo/SKILL.md';
        if (!is_dir(dirname($skillPath))) {
            mkdir(dirname($skillPath), 0777, true);
        }
        file_put_contents($skillPath, implode("\n", [
            '---',
            'name: demo',
            'description: Demo skill',
            '---',
            'Skill body.',
            '',
        ]));

        $library = SkillLibrary::inDirectory($skillsDir);
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::final('ok'),
            ])))
            ->withCapability(new UseSkills($library))
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }
        $messageText = $next->messages()->toString();

        expect($messageText)->toContain('<skills-metadata>');
        expect($messageText)->toContain('[demo]: Demo skill');
    });
});
