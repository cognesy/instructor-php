<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Messages\Messages;

describe('AgentStopException', function () {
    it('records a stop signal when a stop exception is thrown', function () {
        $signal = new StopSignal(
            reason: StopReason::Completed,
            message: 'stop now',
            source: 'TestStop',
        );

        $driver = new class($signal) implements CanUseTools {
            public function __construct(private StopSignal $signal) {}

            public function useTools(AgentState $state): AgentState {
                $step = new AgentStep(inputMessages: $state->messages());
                throw new AgentStopException($this->signal, $step, source: 'TestStop');
            }
        };

        $events = [];

        $agentLoop = AgentBuilder::base()
            ->withCapability(new UseDriver($driver))
            ->build();

        $agentLoop->wiretap(function (object $event) use (&$events): void {
            if ($event instanceof ContinuationEvaluated) {
                $events[] = $event;
            }
        });

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        $finalState = null;
        foreach ($agentLoop->iterate($state) as $stepState) {
            $finalState = $stepState;
        }

        expect($finalState)->not->toBeNull();
        expect($finalState->status())->toBe(ExecutionStatus::Stopped);
        // The step from AgentStopException is not recorded on state by the loop;
        // handleStopException only extracts the stop signal, not the step.
        expect($finalState->stepCount())->toBe(0);
        // fromStopException always maps to StopRequested, with source from the exception
        expect($finalState->lastStopReason())->toBe(StopReason::StopRequested);
        expect($finalState->lastStopSource())->toBe('TestStop');
        expect($events)->toHaveCount(1);
        expect($events[0]->stopReason())->toBe(StopReason::StopRequested);
        expect($events[0]->resolvedBy())->toBe('TestStop');
    });
});
