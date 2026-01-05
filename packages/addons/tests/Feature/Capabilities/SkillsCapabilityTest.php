<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Skills\LoadSkillTool;
use Cognesy\Addons\Agent\Capabilities\Skills\UseSkills;

describe('Skills Capability', function () {
    it('installs Skills capability correctly', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseSkills())
            ->build();
            
        expect($agent->tools()->has('load_skill'))->toBeTrue();
        expect($agent->tools()->get('load_skill'))->toBeInstanceOf(LoadSkillTool::class);
    });
});
