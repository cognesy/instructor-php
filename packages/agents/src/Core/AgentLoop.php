<?php declare(strict_types=1);

namespace Cognesy\Agents\Core;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Core\Contracts\CanReportObserverState;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\CurrentExecution;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Lifecycle\CanObserveAgentLifecycle;
use Cognesy\Agents\Core\Lifecycle\ErrorRecorder;
use Cognesy\Agents\Core\Lifecycle\StepRecorder;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use DateTimeImmutable;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and continuation evaluations.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on aggregated evaluations.
 */
class AgentLoop implements CanControlAgentLoop
{
    private readonly StepRecorder $stepRecorder;
    private readonly ErrorRecorder $errorRecorder;

    public function __construct(
        private readonly Tools $tools,
        private readonly CanExecuteToolCalls $toolExecutor,
        private readonly CanHandleAgentErrors $errorHandler,
        private readonly CanUseTools $driver,
        private readonly CanEmitAgentEvents $eventEmitter,
        private readonly ?CanObserveAgentLifecycle $observer = null,
    ) {
        $this->stepRecorder = new StepRecorder($this->eventEmitter);
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
                $state = $state->withNewStepExecution();
                if (!$this->shouldContinue($state)) {
                    yield $this->onAfterExecution($state);
                    return;
                }
                $state = $this->onBeforeStep($state);

                // Check if guard hooks forbade continuation (Task 1 & 3: evaluation-based flow)
                if ($this->isContinuationForbidden($state)) {
                    yield $this->onAfterExecution($state);
                    return;
                }

                $state = $this->performStep($state);
                $state = $this->onAfterStep($state);
                $state = $this->aggregateAndClearEvaluations($state, emitEvent: false);
                $state = $this->recordStep($state);
                $this->eventEmitter->stepCompleted($state);
            } catch (\LogicException $error) {
                throw $error; // Programming errors propagate
            } catch (Throwable $error) {
                $state = $this->onError($error, $state);
            }

            if ($this->shouldContinue($state)) {
                $state = $state->withClearedCurrentExecution();
                yield $state;
                continue;
            }

            $state = $state->withClearedCurrentExecution();
            yield $this->onAfterExecution($state);
            return;
        }
    }

    // LIFECYCLE HOOKS ////////////////////////////////////

    protected function onBeforeExecution(AgentState $state): AgentState {
        $executionStartedAt = new DateTimeImmutable();
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
            // Only aggregate if a hook forbids continuation before work begins.
            $state = $this->aggregateForbiddenEvaluations($state);
        }

        return $state;
    }

    private function onBeforeToolUse(AgentState $state) : AgentState {
        return $state;
    }

    protected function onAfterStep(AgentState $state): AgentState {
        if ($this->observer !== null) {
            $state = $this->observer->onAfterStep($state);
        }

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
        $result = $this->errorRecorder->record(
            $error,
            $state,
            $currentExecution,
        );

        $nextState = $result->state;

        if ($this->observer !== null) {
            $nextState = $this->observer->onError($nextState, $result->exception);
        }

        return $this->finalizeErrorOutcome($nextState);
    }

    // INTERNAL ///////////////////////////////////////////

    protected function shouldContinue(AgentState $state): bool {
        // Check basic continuation conditions
        if ($state->status() === AgentStatus::Failed) {
            return false;
        }

        $outcome = $state->continuationOutcome();
        if ($outcome !== null) {
            return $outcome->shouldContinue();
        }

        return $state->stepCount() === 0;
    }

    private function performStep(AgentState $state): AgentState {
        return $this->useTools($state);
    }

    private function recordStep(AgentState $state): AgentState
    {
        $currentExecution = $this->resolveCurrentExecution($state);
        $currentStep = $state->currentStep();
        if ($currentStep === null) {
            throw new \LogicException('Current step is missing. This indicates a lifecycle bug.');
        }

        $pendingOutcome = $state->pendingOutcome();
        $outcome = $pendingOutcome ?? ContinuationOutcome::empty();

        return $this->stepRecorder->record(
            execution: $currentExecution,
            state: $state,
            step: $currentStep,
            outcome: $outcome,
        );
    }

    private function useTools(AgentState $state): AgentState {
        $state = $state->withHookContextCleared();

        $state = $this->onBeforeToolUse($state);
        $rawStep = $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );

        $state = $this->applyObserverState($this->driver, $state);
        $state = $this->applyObserverState($this->toolExecutor, $state);

        return $state->withCurrentStep($rawStep);
    }

    private function resolveCurrentExecution(AgentState $state): CurrentExecution {
        $currentExecution = $state->currentExecution();
        if ($currentExecution !== null) {
            return $currentExecution;
        }
        throw new \LogicException('Current execution is missing. This indicates a lifecycle bug.');
    }

    /**
     * Aggregate hook evaluations into a ContinuationOutcome and clear them.
     *
     * This is the single emission point for continuationEvaluated when hooks
     * have accumulated evaluations. If no evaluations exist, returns state unchanged.
     *
     * @param bool $emitEvent Whether to emit the continuationEvaluated event
     */
    private function aggregateAndClearEvaluations(AgentState $state, bool $emitEvent = true): AgentState
    {
        if (!$state->hasEvaluations()) {
            return $state;
        }

        // Aggregate evaluations into outcome
        $outcome = ContinuationOutcome::fromEvaluations($state->evaluations());

        // Clear evaluations and set outcome
        $state = $state
            ->withEvaluationsCleared()
            ->withContinuationOutcome($outcome);

        // Emit event after state has outcome
        if ($emitEvent) {
            $this->eventEmitter->continuationEvaluated($state);
        }

        return $state;
    }

    /**
     * Aggregate evaluations only when a hook forbids continuation pre-step.
     */
    private function aggregateForbiddenEvaluations(AgentState $state): AgentState
    {
        if (!$state->hasEvaluations()) {
            return $state;
        }
        if (!$this->hasForbidEvaluation($state->evaluations())) {
            return $state;
        }

        return $this->aggregateAndClearEvaluations($state);
    }

    /**
     * @param list<\Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation> $evaluations
     */
    private function hasForbidEvaluation(array $evaluations): bool
    {
        foreach ($evaluations as $evaluation) {
            if ($evaluation->decision === ContinuationDecision::ForbidContinuation) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if guard hooks have forbidden continuation.
     *
     * This is the authoritative check point for evaluation-based flow control.
     * When guard hooks write ForbidContinuation evaluations in BeforeStep,
     * this method detects that and short-circuits execution.
     */
    private function isContinuationForbidden(AgentState $state): bool
    {
        $outcome = $state->pendingOutcome();
        if ($outcome === null) {
            return false;
        }

        // ForbidContinuation is authoritative - stop immediately
        return $outcome->isForbidden();
    }

    private function applyObserverState(object $source, AgentState $state): AgentState
    {
        if (!$source instanceof CanReportObserverState) {
            return $state;
        }

        return $source->observerState() ?? $state;
    }

    private function finalizeErrorOutcome(AgentState $state): AgentState
    {
        $lastExecution = $state->stepExecutions()->last();
        if ($lastExecution === null) {
            return $state;
        }

        $baseOutcome = $lastExecution->outcome;
        if (!$state->hasEvaluations()) {
            $this->eventEmitter->continuationEvaluated($state);
            return $state;
        }

        $mergedEvaluations = [...$baseOutcome->evaluations, ...$state->evaluations()];
        $outcome = ContinuationOutcome::fromEvaluations($mergedEvaluations);

        $updatedExecution = new StepExecution(
            step: $lastExecution->step,
            outcome: $outcome,
            startedAt: $lastExecution->startedAt,
            completedAt: $lastExecution->completedAt,
            stepNumber: $lastExecution->stepNumber,
            id: $lastExecution->id,
        );

        $nextState = $state
            ->withStepExecutionReplaced($updatedExecution)
            ->withEvaluationsCleared()
            ->withContinuationOutcome($outcome);

        $this->eventEmitter->continuationEvaluated($nextState);
        $this->eventEmitter->stateUpdated($nextState);

        return $nextState;
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
            driver: $driver ?? $this->driver,
            eventEmitter: $eventEmitter ?? $this->eventEmitter,
            observer: $observer ?? $this->observer,
        );
    }
}
