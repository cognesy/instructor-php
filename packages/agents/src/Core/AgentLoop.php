<?php declare(strict_types=1);

namespace Cognesy\Agents\Core;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\CurrentExecution;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Lifecycle\CanObserveAgentLifecycle;
use Cognesy\Agents\Core\Lifecycle\ErrorRecorder;
use Cognesy\Agents\Core\Lifecycle\StepRecorder;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use DateTimeImmutable;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and continuation criteria.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on the current state.
 */
class AgentLoop implements CanControlAgentLoop
{
    private readonly StepRecorder $stepRecorder;
    private readonly ErrorRecorder $errorRecorder;

    public function __construct(
        private readonly Tools $tools,
        private readonly CanExecuteToolCalls $toolExecutor,
        private readonly CanHandleAgentErrors $errorHandler,
        private readonly ContinuationCriteria $continuationCriteria,
        private readonly CanUseTools $driver,
        private readonly CanEmitAgentEvents $eventEmitter,
        private readonly ?CanObserveAgentLifecycle $observer = null,
    ) {
        $this->stepRecorder = new StepRecorder($this->continuationCriteria, $this->eventEmitter);
        $this->errorRecorder = new ErrorRecorder($this->errorHandler, $this->eventEmitter);
    }

    // PUBLIC API //////////////////////////////////

    #[\Override]
    public function execute(AgentState $state): AgentState {
        $finalState = $state;
        foreach ($this->iterate($state) as $stepState) {
            $finalState = $stepState;
        }
        return $finalState;
    }

    #[\Override]
    public function iterate(AgentState $state): iterable {
        $state = $this->onBeforeExecution($state);

        while (true) {
            try {
                $state = $state->beginStepExecution();
                if (!$this->shouldContinue($state)) {
                    $state = $state->clearCurrentExecution();
                    break;
                }
                $state = $this->onBeforeStep($state);
                $state = $this->performStep($state);
                $state = $this->onAfterStep($state);
            } catch (\LogicException $error) {
                throw $error; // Programming errors propagate
            } catch (Throwable $error) {
                $state = $this->onError($error, $state);
            }
            yield $state;
        }

        $finalState = $this->onAfterExecution($state);
        if ($this->hasUnprocessedSteps($state, $finalState)) {
            yield $finalState;
        }
    }

    // LIFECYCLE HOOKS ////////////////////////////////////

    protected function onBeforeExecution(AgentState $state): AgentState {
        $executionStartedAt = new DateTimeImmutable();
        $this->continuationCriteria->executionStarted($executionStartedAt);
        $this->eventEmitter->executionStarted($state, count($this->tools->names()));

        if ($this->observer !== null) {
            $state = $this->observer->onBeforeExecution($state);
        }

        return $state;
    }

    protected function onBeforeStep(AgentState $state): AgentState {
        $this->eventEmitter->stepStarted($state);

        if ($this->observer !== null) {
            $state = $this->observer->onBeforeStep($state);
        }

        return $state;
    }

    private function onBeforeToolUse(AgentState $state) : AgentState {
        return $state;
    }

    private function onAfterToolUse(AgentState $state, AgentStep $rawStep) : AgentState
    {
        $currentExecution = $this->resolveCurrentExecution($state);
        return $this->stepRecorder->record($currentExecution, $state, $rawStep);
    }

    protected function onAfterStep(AgentState $state): AgentState {
        if ($this->observer !== null) {
            $state = $this->observer->onAfterStep($state);
        }

        $this->eventEmitter->stepCompleted($state);
        return $state;
    }

    protected function onAfterExecution(AgentState $state): AgentState {
        $status = match ($state->stopReason()) {
            StopReason::ErrorForbade => AgentStatus::Failed,
            default => AgentStatus::Completed,
        };

        $finalState = $state->withStatus($status);

        if ($this->observer !== null) {
            $finalState = $this->observer->onAfterExecution($finalState);
        }

        $this->eventEmitter->executionFinished($finalState);
        return $finalState;
    }

    protected function onError(Throwable $error, AgentState $state): AgentState {
        $currentExecution = $this->resolveCurrentExecution($state);
        $result = $this->errorRecorder->record($error, $state, $currentExecution);

        if ($this->observer === null) {
            return $result->state;
        }

        if (!$result->isFailed) {
            return $result->state;
        }

        return $this->observer->onError($result->state, $result->exception);
    }

    // INTERNAL ///////////////////////////////////////////

    protected function shouldContinue(AgentState $state): bool {
        // Check basic continuation conditions
        if ($state->status() === AgentStatus::Failed) {
            return false;
        }

        $shouldContinue = $state->stepCount() === 0
            ? $this->continuationCriteria->canContinue($state)
            : $state->stepExecutions()->shouldContinue();

        if ($shouldContinue) {
            return true;
        }

        // We're about to stop - let the observer intervene if registered
        if ($this->observer !== null) {
            $stopReason = $state->stopReason() ?? StopReason::Completed;
            $decision = $this->observer->onBeforeStopDecision($state, $stopReason);
            if ($decision->isPrevented()) {
                return true; // Observer prevented stopping, continue execution
            }
        }

        return false;
    }

    private function performStep(AgentState $state): AgentState {
        return $this->useTools($state);
    }

    private function useTools(AgentState $state): AgentState {
        $currentExecution = $this->resolveCurrentExecution($state);
        $state = $state->withCurrentExecution(new CurrentExecution(
            stepNumber: $currentExecution->stepNumber,
            startedAt: new DateTimeImmutable(),
            id: $currentExecution->id,
        ));
        $state = $this->onBeforeToolUse($state);
        $rawStep = $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );
        return $this->onAfterToolUse($state, $rawStep);
    }

    private function hasUnprocessedSteps(AgentState $state, AgentState $finalState) : bool {
        return $state->stepCount() !== $finalState->stepCount();
    }

    private function resolveCurrentExecution(AgentState $state): CurrentExecution {
        $currentExecution = $state->currentExecution();
        if ($currentExecution !== null) {
            return $currentExecution;
        }
        throw new \LogicException('Current execution is missing. This indicates a lifecycle bug.');
    }

    // EVENT DELEGATION ///////////////////////////////////

    /**
     * @param callable(object): void $listener
     */
    public function wiretap(callable $listener): self {
        $this->eventEmitter->wiretap($listener);
        return $this;
    }

    /**
     * @param class-string $eventClass
     * @param callable(object): void $listener
     */
    public function onEvent(string $eventClass, callable $listener): self {
        $this->eventEmitter->onEvent($eventClass, $listener);
        return $this;
    }

    // ACCESSORS ////////////////////////////////////////////

    public function tools(): Tools {
        return $this->tools;
    }

    public function toolExecutor(): CanExecuteToolCalls {
        return $this->toolExecutor;
    }

    public function errorHandler(): CanHandleAgentErrors {
        return $this->errorHandler;
    }

    public function driver(): CanUseTools {
        return $this->driver;
    }

    public function eventEmitter(): CanEmitAgentEvents {
        return $this->eventEmitter;
    }

    public function observer(): ?CanObserveAgentLifecycle {
        return $this->observer;
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?Tools $tools = null,
        ?CanExecuteToolCalls $toolExecutor = null,
        ?CanHandleAgentErrors $errorHandler = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanEmitAgentEvents $eventEmitter = null,
        ?CanObserveAgentLifecycle $observer = null,
    ): self {
        $resolvedTools = $tools ?? $this->tools;

        // If tools changed but no executor provided, create new executor
        $resolvedExecutor = $toolExecutor ?? (
            $tools !== null
                ? new ToolExecutor($resolvedTools)
                : $this->toolExecutor
        );

        return new self(
            tools: $resolvedTools,
            toolExecutor: $resolvedExecutor,
            errorHandler: $errorHandler ?? $this->errorHandler,
            continuationCriteria: $continuationCriteria ?? $this->continuationCriteria,
            driver: $driver ?? $this->driver,
            eventEmitter: $eventEmitter ?? $this->eventEmitter,
            observer: $observer ?? $this->observer,
        );
    }
}
