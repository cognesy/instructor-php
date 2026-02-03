<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique\SelfCriticHook;
use Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique\SelfCriticResult;
use Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique\UseSelfCritique;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

describe('SelfCritique Capability', function () {
    it('forbids continuation deterministically when max iterations reached', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new FakeAgentDriver([
                ScenarioStep::final('ok'),
            ]))
            ->withCapability(new UseSelfCritique(maxIterations: 0, verbose: false))
            ->build();

        $state = AgentState::empty()
            ->withMetadata(
                SelfCriticHook::METADATA_KEY,
                new SelfCriticResult(false, 'Needs revision'),
            )
            ->withMetadata(SelfCriticHook::ITERATION_KEY, 0);

        // Get first step from iterate()
        $next = null;
        foreach ($agent->iterate($state) as $stepState) {
            $next = $stepState;
            break;
        }

        expect($next->lastStopReason())->toBe(StopReason::RetryLimitReached);
    });
});
