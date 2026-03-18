<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Capabilities;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Capability\Cancellation\UseCooperativeCancellation;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Messages\Messages;

describe('Cooperative cancellation capability', function () {
    it('stops before the first step when cancellation is already requested', function () {
        $source = new InMemoryCancellationSource();
        $source->cancel('cancel before execution');

        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))
            ->withCapability(new UseCooperativeCancellation($source))
            ->build();

        $finalState = $agent->execute(
            AgentState::empty()->withMessages(Messages::fromString('ping')),
        );

        expect($finalState->status())->toBe(ExecutionStatus::Stopped)
            ->and($finalState->stepCount())->toBe(0)
            ->and($finalState->stopReason())->toBe(StopReason::UserRequested)
            ->and($source->isCancellationRequested())->toBeTrue();
    });

    it('stops at the next before-step checkpoint after cancellation is requested mid-run', function () {
        $source = new InMemoryCancellationSource();
        $completedSteps = 0;
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
                ScenarioStep::toolCall('demo_tool', executeTools: false),
                ScenarioStep::final('done'),
            )))
            ->withCapability(new UseCooperativeCancellation($source))
            ->build();

        $agent->onEvent(AgentStepCompleted::class, function () use ($source, &$completedSteps): void {
            $completedSteps++;
            if ($completedSteps === 1) {
                $source->cancel('cancel before second step');
            }
        });

        $finalState = $agent->execute(
            AgentState::empty()->withMessages(Messages::fromString('run')),
        );

        expect($completedSteps)->toBe(1);
        expect($finalState->status())->toBe(ExecutionStatus::Stopped)
            ->and($finalState->stepCount())->toBe(1)
            ->and($finalState->stopReason())->toBe(StopReason::UserRequested);
    });
});
