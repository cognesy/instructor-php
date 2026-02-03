<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Stop\AgentStopException;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
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

            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState {
                $step = new AgentStep(inputMessages: $state->messages());
                throw new AgentStopException($this->signal, $step, source: 'TestStop');
            }
        };

        $events = [];

        $agentLoop = AgentBuilder::base()
            ->withDriver($driver)
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
        expect($finalState->status())->toBe(ExecutionStatus::Completed);
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
