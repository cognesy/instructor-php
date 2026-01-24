<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique\SelfCriticContinuationCheck;
use Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique\SelfCriticResult;
use Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique\UseSelfCritique;
use Cognesy\Agents\Drivers\Testing\DeterministicAgentDriver;

describe('SelfCritique Capability', function () {
    it('forbids continuation deterministically when max iterations reached', function () {
        $agent = AgentBuilder::base()
            ->withDriver(new DeterministicAgentDriver([
                ScenarioStep::final('ok'),
            ]))
            ->withCapability(new UseSelfCritique(maxIterations: 0, verbose: false))
            ->build();

        $state = AgentState::empty()
            ->withMetadata(
                SelfCriticContinuationCheck::METADATA_KEY,
                new SelfCriticResult(false, 'Needs revision'),
            )
            ->withMetadata(SelfCriticContinuationCheck::ITERATION_KEY, 0);

        $next = $agent->nextStep($state);

        expect($next->stopReason())->toBe(StopReason::RetryLimitReached);
    });
});
