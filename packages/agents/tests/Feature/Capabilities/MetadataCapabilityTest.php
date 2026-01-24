<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Metadata\UseMetadataTools;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;

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
