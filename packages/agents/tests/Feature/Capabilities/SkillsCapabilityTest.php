<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\SkillLibrary;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\UseSkills;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;

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
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::final('ok'),
            ]))
            ->withCapability(new UseSkills($library))
            ->build();

        $next = $agent->nextStep(AgentState::empty());
        $messageText = $next->messages()->toString();

        expect($messageText)->toContain('<skills-metadata>');
        expect($messageText)->toContain('[demo]: Demo skill');
    });
});
