<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Capabilities\Metadata\UseMetadataTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;

describe('Metadata Capability', function () {
    it('persists metadata deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::toolCall('store_metadata', [
                    'key' => 'current_lead',
                    'value' => ['name' => 'Ada'],
                ], executeTools: true),
            ]))
            ->withCapability(new UseMetadataTools())
            ->build();

        $next = $agent->nextStep(AgentState::empty());

        expect($next->metadata()->get('current_lead'))->toBe(['name' => 'Ada']);
    });
});
