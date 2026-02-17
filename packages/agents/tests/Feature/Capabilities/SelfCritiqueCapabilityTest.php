<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\SelfCritique\SelfCriticHook;
use Cognesy\Agents\Capability\SelfCritique\SelfCriticResult;
use Cognesy\Agents\Capability\SelfCritique\UseSelfCritique;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\PendingStructuredOutput;

describe('SelfCritique Capability', function () {
    it('forbids continuation deterministically when max iterations reached', function () {
        $creator = new class implements CanCreateStructuredOutput {
            #[\Override]
            public function create(StructuredOutputRequest $request): PendingStructuredOutput {
                throw new \RuntimeException('Not used in this test.');
            }
        };

        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([
                ScenarioStep::final('ok'),
            ])))
            ->withCapability(new UseSelfCritique(
                structuredOutput: $creator,
                maxIterations: 0,
            ))
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
