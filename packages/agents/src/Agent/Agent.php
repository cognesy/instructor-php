<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent;

use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Contracts\CanExecuteIteratively;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Contracts\ToolInterface;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Data\StepResult;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Agents\Agent\Events\AgentFailed;
use Cognesy\Agents\Agent\Events\AgentFinished;
use Cognesy\Agents\Agent\Events\AgentStateUpdated;
use Cognesy\Agents\Agent\Events\AgentStepCompleted;
use Cognesy\Agents\Agent\Events\AgentStepStarted;
use Cognesy\Agents\Agent\Events\ContinuationEvaluated;
use Cognesy\Agents\Agent\Events\TokenUsageReported;
use Cognesy\Agents\Agent\Exceptions\AgentException;
use Cognesy\Agents\Agent\StateProcessing\CanApplyProcessors;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Agents\Agent\StateProcessing\StateProcessors;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use DateTimeImmutable;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and continuation criteria.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on the current state.
 * It integrates with event handling to provide feedback on the process and supports
 * state processing to modify the state after each step.
 *
 * @implements CanExecuteIteratively<AgentState>
 */
class Agent implements CanExecuteIteratively
{
    use HandlesEvents;

    private readonly Tools $tools;
    private readonly CanExecuteToolCalls $toolExecutor;
    private readonly CanUseTools $driver;
    private readonly ContinuationCriteria $continuationCriteria;
    protected ?CanApplyProcessors $processors;

    public function __construct(
        Tools $tools,
        CanExecuteToolCalls $toolExecutor,
        ?CanApplyProcessors $processors,
        ContinuationCriteria $continuationCriteria,
        CanUseTools $driver,
        ?CanHandleEvents $events,
    ) {
        $this->processors = $processors;
        $this->continuationCriteria = $continuationCriteria;
        $this->driver = $driver;
        $this->events = EventBusResolver::using($events);
        $this->tools = $tools;
        $this->toolExecutor = $toolExecutor;
    }

    // PUBLIC API (from CanExecuteIteratively) ///////////////////////

    /**
     * Advance to the next step in the iterative process.
     */
    #[\Override]
    public function nextStep(object $state): AgentState {
        assert($state instanceof AgentState);
        return match(true) {
            ($this->hasProcessors()) => $this->performThroughProcessors($state),
            default => $this->performStep($state),
        };
    }

    /**
     * Determine whether there is a next step to execute.
     */
    #[\Override]
    public function hasNextStep(object $state): bool {
        assert($state instanceof AgentState);
        return $this->canContinue($state);
    }

    /**
     * Perform all steps and return the final state.
     */
    #[\Override]
    public function finalStep(object $state): AgentState {
        assert($state instanceof AgentState);
        // Mark execution start time for ExecutionTimeLimit.
        // This resets the clock for each new execution (user query),
        // preventing timeouts in multi-turn conversations spanning days.
        $state = $this->markExecutionStarted($state);

        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }
        return $this->onNoNextStep($state);
    }

    /**
     * Create an iterator to traverse through each step.
     *
     * @return iterable<AgentState>
     */
    #[\Override]
    public function iterator(object $state): iterable {
        assert($state instanceof AgentState);
        // Mark execution start time for ExecutionTimeLimit.
        // This resets the clock for each new execution (user query),
        // preventing timeouts in multi-turn conversations spanning days.
        $state = $this->markExecutionStarted($state);

        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }

        $finalState = $this->onNoNextStep($state);
        if ($this->isStateChanged($state, $finalState)) {
            yield $finalState;
        }
    }

    // INTERNAL - STEP EXECUTION /////////////////////////////////////////////

    /**
     * Check if we should continue.
     * Pre-evaluates criteria before the first step, then reads from StepResult.
     */
    protected function canContinue(AgentState $state): bool {
        $stepCount = $state->stepCount();
        if ($stepCount === 0) {
            return $this->continuationCriteria->canContinue($state);
        }

        $lastResult = $state->lastStepResult();
        $stepResultsCount = count($state->stepResults());
        if ($lastResult === null || $stepResultsCount !== $stepCount) {
            throw new \LogicException(sprintf(
                'Step results count (%d) does not match step count (%d).',
                $stepResultsCount,
                $stepCount,
            ));
        }

        return $lastResult->shouldContinue();
    }

    /**
     * Create a raw step from the driver (without continuation outcome).
     */
    protected function makeNextStep(AgentState $state): AgentStep {
        $this->emitAgentStepStarted($state);
        return $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );
    }

    /**
     * Apply a complete step (with outcome) to the state.
     */
    protected function applyStep(AgentState $state, AgentStep $nextStep): AgentState {
        $newState = $state->recordStep($nextStep);
        $this->emitAgentStateUpdated($newState);
        return $newState;
    }

    protected function onNoNextStep(AgentState $state): AgentState {
        $finalStatus = $this->determineFinalStatus($state);
        $finalState = $state->withStatus($finalStatus);
        $this->emitAgentFinished($finalState);
        return $finalState;
    }

    private function determineFinalStatus(AgentState $state): AgentStatus {
        $stopReason = $state->stopReason();
        return match ($stopReason) {
            StopReason::ErrorForbade => AgentStatus::Failed,
            default => AgentStatus::Completed,
        };
    }

    protected function onStepCompleted(AgentState $state): AgentState {
        $this->emitAgentStepCompleted($state);
        return $state;
    }

    protected function onFailure(Throwable $error, AgentState $state): AgentState {
        $failure = $error instanceof AgentException
            ? $error
            : AgentException::fromThrowable($error);
        $failureStep = AgentStep::failure(
            inputMessages: $state->messages(),
            error: $failure,
        );
        $transitionState = $state
            ->withStatus(AgentStatus::Failed)
            ->recordStep($failureStep)
            ->withAccumulatedUsage($failureStep->usage());
        $outcome = $this->evaluateOutcomeOnFailure($transitionState);
        $stepResult = new StepResult($failureStep, $outcome);
        $failedState = $state
            ->withStatus(AgentStatus::Failed)
            ->recordStepResult($stepResult)
            ->withAccumulatedUsage($failureStep->usage());
        $this->emitAgentStateUpdated($failedState);
        $this->emitAgentFailed($failedState, $failure);
        return $failedState;
    }

    /**
     * Perform a single step: create step, evaluate continuation, bundle into StepResult, record.
     */
    protected function performStep(AgentState $state): AgentState {
        try {
            $stepStartedAt = microtime(true);
            $stateWithStart = $this->markStepStarted($state);

            // 1. Create raw step from driver
            $rawStep = $this->makeNextStep($stateWithStart);

            // 2. Create transition state with step recorded (for correct stepCount during evaluation)
            // Also accumulate usage so TokenUsageLimit can evaluate correctly
            $transitionState = $stateWithStart
                ->recordStep($rawStep)
                ->withAccumulatedUsage($rawStep->usage());

            // 3. Evaluate continuation criteria on state with this step
            $outcome = $this->continuationCriteria->evaluateAll($transitionState);
            $this->emitContinuationEvaluated($transitionState, $outcome);

            // 4. Bundle step + outcome into StepResult
            $stepResult = new StepResult($rawStep, $outcome);

            // 5. Record the StepResult to the original state
            $nextState = $stateWithStart->recordStepResult($stepResult);
            $this->emitAgentStateUpdated($nextState);

            $durationSeconds = microtime(true) - $stepStartedAt;
            $nextState = $this->addExecutionTimeIfSupported($nextState, $durationSeconds);
            return $this->onStepCompleted($nextState);
        } catch (Throwable $error) {
            return $this->onFailure($error, $state);
        }
    }

    // INTERNAL - PROCESSOR SUPPORT /////////////////////////////////////

    protected function hasProcessors(): bool {
        return $this->processors !== null;
    }

    protected function performThroughProcessors(AgentState $state): AgentState {
        try {
            assert($this->processors !== null);
            return $this->processors->apply(
                $state,
                function(AgentState $state): AgentState {
                    return $this->performStep($state);
                }
            );
        } catch (Throwable $error) {
            return $this->onFailure($error, $state);
        }
    }

    /**
     * Mark execution start time.
     * This is used by ExecutionTimeLimit to measure per-execution time,
     * not session lifetime.
     */
    protected function markExecutionStarted(AgentState $state): AgentState {
        return $state->markExecutionStarted();
    }

    /**
     * Only consider state changed if a new step was added.
     * Status-only changes (e.g., InProgress → Completed) don't represent new work.
     */
    protected function isStateChanged(AgentState $priorState, AgentState $newState): bool {
        return $priorState->stepCount() !== $newState->stepCount();
    }

    // ACCESSORS ////////////////////////////////////////////

    public function tools() : Tools {
        return $this->tools;
    }

    public function toolExecutor(): CanExecuteToolCalls {
        return $this->toolExecutor;
    }

    public function driver() : CanUseTools {
        return $this->driver;
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?Tools $tools = null,
        ?CanExecuteToolCalls $toolExecutor = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ) : self {
        $resolvedProcessors = $processors ?? $this->processors;
        $resolvedTools = $tools ?? $this->tools;
        $resolvedEvents = $events ?? $this->events;

        // If tools changed but no executor provided, create a new executor for the new tools
        $resolvedExecutor = $toolExecutor ?? (
            $tools !== null
                ? (new ToolExecutor($resolvedTools))->withEventHandler($resolvedEvents)
                : $this->toolExecutor
        );

        return new self(
            tools: $resolvedTools,
            toolExecutor: $resolvedExecutor,
            processors: $resolvedProcessors,
            continuationCriteria: $continuationCriteria ?? $this->continuationCriteria,
            driver: $driver ?? $this->driver,
            events: $resolvedEvents,
        );
    }

    public function withProcessors(CanProcessAgentState ...$processors): self {
        return $this->with(processors: new StateProcessors(...$processors));
    }

    public function withDriver(CanUseTools $driver) : self {
        return $this->with(driver: $driver);
    }

    public function withContinuationCriteria(CanEvaluateContinuation ...$continuationCriteria) : self {
        return $this->with(continuationCriteria: new ContinuationCriteria(...$continuationCriteria));
    }

    public function withTools(array|ToolInterface|Tools $tools) : self {
        return $this->with(tools: match(true) {
            is_array($tools) => new Tools(...$tools),
            $tools instanceof ToolInterface => new Tools($tools),
            $tools instanceof Tools => $tools,
            default => new Tools(),
        });
    }

    public function withToolExecutor(CanExecuteToolCalls $toolExecutor) : self {
        return $this->with(toolExecutor: $toolExecutor);
    }

    // EVENTS ////////////////////////////////////////////

    private function emitAgentFinished(AgentState $state) : void {
        $this->events->dispatch(new AgentFinished(
            agentId: $state->agentId,
            parentAgentId: $state->parentAgentId,
            status: $state->status(),
            totalSteps: $state->stepCount(),
            totalUsage: $state->usage(),
            errors: $state->currentStep()?->errorsAsString(),
        ));
    }

    private function emitAgentStepStarted(AgentState $state) : void {
        $this->events->dispatch(new AgentStepStarted(
            agentId: $state->agentId,
            parentAgentId: $state->parentAgentId,
            stepNumber: $state->stepCount() + 1,
            messageCount: $state->messages()->count(),
            availableTools: count($this->tools->names()),
        ));
    }

    private function emitAgentStepCompleted(AgentState $state) : void {
        $usage = $state->currentStep()?->usage() ?? new \Cognesy\Polyglot\Inference\Data\Usage(0, 0);

        $this->events->dispatch(new AgentStepCompleted(
            agentId: $state->agentId,
            parentAgentId: $state->parentAgentId,
            stepNumber: $state->stepCount(),
            hasToolCalls: $state->currentStep()?->hasToolCalls() ?? false,
            errorCount: count($state->currentStep()?->errors() ?? []),
            errorMessages: $state->currentStep()?->errorsAsString() ?? '',
            usage: $usage,
            finishReason: $state->currentStep()?->finishReason(),
            startedAt: $state->currentStepStartedAt ?? new DateTimeImmutable(),
        ));

        // Report token usage
        if ($usage->total() > 0) {
            $this->events->dispatch(new TokenUsageReported(
                agentId: $state->agentId,
                parentAgentId: $state->parentAgentId,
                operation: 'step',
                usage: $usage,
                context: [
                    'step' => $state->stepCount(),
                    'hasToolCalls' => $state->currentStep()?->hasToolCalls() ?? false,
                ],
            ));
        }
    }

    private function emitAgentStateUpdated(AgentState $state) : void {
        $this->events->dispatch(new AgentStateUpdated(
            agentId: $state->agentId,
            parentAgentId: $state->parentAgentId,
            status: $state->status(),
            stepCount: $state->stepCount(),
            stateSnapshot: $state->toArray(),
            currentStepSnapshot: $state->currentStep()?->toArray() ?? [],
        ));
    }

    private function emitAgentFailed(AgentState $failedState, AgentException $exception) : void {
        $this->events->dispatch(new AgentFailed(
            agentId: $failedState->agentId,
            parentAgentId: $failedState->parentAgentId,
            exception: $exception,
            status: $failedState->status(),
            stepsCompleted: $failedState->stepCount(),
            totalUsage: $failedState->usage(),
            errors: $failedState->currentStep()?->errorsAsString(),
        ));
    }

    private function emitContinuationEvaluated(AgentState $state, ContinuationOutcome $outcome) : void {
        $this->events->dispatch(new ContinuationEvaluated(
            agentId: $state->agentId,
            parentAgentId: $state->parentAgentId,
            stepNumber: $state->stepCount(),
            outcome: $outcome,
        ));
    }

    private function evaluateOutcomeOnFailure(AgentState $state): ContinuationOutcome {
        try {
            return $this->continuationCriteria->evaluateAll($state);
        } catch (Throwable $error) {
            return ContinuationOutcome::fromEvaluationError($error);
        }
    }

    private function markStepStarted(AgentState $state): AgentState {
        return $state->markStepStarted();
    }

    private function addExecutionTimeIfSupported(AgentState $state, float $seconds): AgentState {
        return $state->withAddedExecutionTime($seconds);
    }
}
