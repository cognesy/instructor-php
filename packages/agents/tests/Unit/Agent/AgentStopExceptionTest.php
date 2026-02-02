<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Stop\AgentStopException;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Messages\Messages;
use tmp\ErrorHandling\AgentErrorHandler;

describe('AgentStopException', function () {
    it('records a stop signal when a stop exception is thrown', function () {
        $signal = new StopSignal(
            reason: StopReason::Completed,
            message: 'stop now',
            source: 'TestStop',
        );

        $driver = new class($signal) implements CanUseTools {
            public function __construct(private StopSignal $signal) {}

            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
                $step = new AgentStep(inputMessages: $state->messages());
                throw new AgentStopException($this->signal, $step);
            }
        };

        $events = [];
        $eventEmitter = new AgentEventEmitter();
        $eventEmitter->wiretap(function (object $event) use (&$events): void {
            if ($event instanceof ContinuationEvaluated) {
                $events[] = $event;
            }
        });

        $tools = new Tools();
        $agentLoop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools),
            errorHandler: AgentErrorHandler::default(),
            driver: $driver,
            eventEmitter: $eventEmitter,
        );

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));

        $finalState = null;
        foreach ($agentLoop->iterate($state) as $stepState) {
            $finalState = $stepState;
        }

        expect($finalState)->not->toBeNull();
        expect($finalState->status())->toBe(AgentStatus::Completed);
        expect($finalState->stepCount())->toBe(1);
        expect($finalState->stopReason())->toBe(StopReason::Completed);
        expect($finalState->lastStepExecution()?->stopSignal?->source)->toBe('TestStop');
        expect($events)->toHaveCount(1);
        expect($events[0]->stopReason())->toBe(StopReason::Completed);
        expect($events[0]->resolvedBy())->toBe('TestStop');
    });
})->skip('hooks not integrated yet');
