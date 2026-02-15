<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Metadata\UseMetadataTools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

describe('Metadata Capability', function () {
    it('persists metadata deterministically through the agent', function () {
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::toolCall('store_metadata', [
                    'key' => 'current_lead',
                    'value' => ['name' => 'Ada'],
                ], executeTools: true),
            ])))
            ->withCapability(new UseMetadataTools())
            ->build();

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate(AgentState::empty()) as $state) {
            $next = $state;
            break;
        }

        expect($next->metadata()->get('current_lead'))->toBe(['name' => 'Ada']);
    });
});
