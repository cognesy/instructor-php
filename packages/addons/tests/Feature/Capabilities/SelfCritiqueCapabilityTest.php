<?php declare(strict_types=1);

namespace Tests\Addons\Feature\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\SelfCritique\SelfCriticContinuationCheck;
use Cognesy\Addons\Agent\Capabilities\SelfCritique\SelfCriticResult;
use Cognesy\Addons\Agent\Capabilities\SelfCritique\UseSelfCritique;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

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
