<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Subagent\SpawnSubagentTool;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;

describe('Subagent Capability', function () {
    it('installs Subagent capability correctly', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseSubagents())
            ->build();
            
        expect($agent->tools()->has('spawn_subagent'))->toBeTrue();
        expect($agent->tools()->get('spawn_subagent'))->toBeInstanceOf(SpawnSubagentTool::class);
    });
});
