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
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStopped;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Messages\Messages;

describe('Agent event payload regression', function () {
    it('emits sequential continuation payloads and completed execution status', function () {
        $driver = FakeAgentDriver::fromSteps(
            ScenarioStep::toolCall('noop', executeTools: false),
            ScenarioStep::final('step two'),
        );

        $events = [];
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver($driver))
            ->build()
            ->wiretap(static function (object $event) use (&$events): void {
                $events[] = $event;
            });

        $final = $agent->execute(
            AgentState::empty()->withMessages(Messages::fromString('ping'))
        );

        $continuations = array_values(array_filter(
            $events,
            static fn(object $event): bool => $event instanceof ContinuationEvaluated,
        ));
        $completed = array_values(array_filter(
            $events,
            static fn(object $event): bool => $event instanceof AgentExecutionCompleted,
        ));

        expect($continuations)->toHaveCount(2);
        expect(array_map(static fn(ContinuationEvaluated $e): int => $e->stepNumber, $continuations))
            ->toBe([1, 2]);
        expect($continuations[1]->shouldStop())->toBeTrue();
        expect($completed)->toHaveCount(1);
        expect($completed[0]->status)->toBe(ExecutionStatus::Completed);
        expect($final->status())->toBe(ExecutionStatus::Completed);
    });

    it('emits stop payload with stop signal reason/source/message', function () {
        $signal = new StopSignal(
            reason: StopReason::Completed,
            message: 'stop now',
            source: 'RegressionStop',
        );

        $driver = new class($signal) implements CanUseTools {
            public function __construct(private StopSignal $signal) {}

            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState {
                $step = new AgentStep(inputMessages: $state->messages());
                throw new AgentStopException($this->signal, $step, source: 'RegressionStop');
            }
        };

        $events = [];
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver($driver))
            ->build()
            ->wiretap(static function (object $event) use (&$events): void {
                $events[] = $event;
            });

        $final = $agent->execute(
            AgentState::empty()->withMessages(Messages::fromString('ping'))
        );

        $continuation = current(array_values(array_filter(
            $events,
            static fn(object $event): bool => $event instanceof ContinuationEvaluated,
        )));
        $stopped = current(array_values(array_filter(
            $events,
            static fn(object $event): bool => $event instanceof AgentExecutionStopped,
        )));
        $completed = current(array_values(array_filter(
            $events,
            static fn(object $event): bool => $event instanceof AgentExecutionCompleted,
        )));

        expect($continuation)->toBeInstanceOf(ContinuationEvaluated::class);
        expect($continuation->stepNumber)->toBe(0);
        expect($continuation->stopSignal())->not->toBeNull();
        expect($continuation->stopSignal()?->reason)->toBe(StopReason::StopRequested);
        expect($continuation->stopSignal()?->source)->toBe('RegressionStop');
        expect($continuation->stopSignal()?->message)->toBe('stop now');
        expect($stopped)->toBeInstanceOf(AgentExecutionStopped::class);
        expect($stopped->stopReason)->toBe(StopReason::StopRequested);
        expect($stopped->stopMessage)->toBe('stop now');
        expect($stopped->source)->toBe('RegressionStop');
        expect($completed)->toBeInstanceOf(AgentExecutionCompleted::class);
        expect($completed->status)->toBe(ExecutionStatus::Stopped);
        expect($final->status())->toBe(ExecutionStatus::Stopped);
    });

    it('emits executionFailed with exception payload on hard error', function () {
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState {
                throw new \RuntimeException('boom regression');
            }
        };

        $events = [];
        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver($driver))
            ->build()
            ->wiretap(static function (object $event) use (&$events): void {
                $events[] = $event;
            });

        $final = $agent->execute(
            AgentState::empty()->withMessages(Messages::fromString('ping'))
        );

        $failed = current(array_values(array_filter(
            $events,
            static fn(object $event): bool => $event instanceof AgentExecutionFailed,
        )));

        expect($failed)->toBeInstanceOf(AgentExecutionFailed::class);
        expect($failed->exception->getMessage())->toBe('boom regression');
        expect($failed->exception)->toBeInstanceOf(\RuntimeException::class);
        expect($failed->status)->toBe(ExecutionStatus::Failed);
        expect($final->status())->toBe(ExecutionStatus::Failed);
    });
});
