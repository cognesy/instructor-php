<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent;

use Cognesy\Addons\Agent\Contracts\ToolInterface;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
use Cognesy\Addons\Agent\Core\ToolExecutor;
use Cognesy\Addons\Agent\Events\AgentFailed;
use Cognesy\Addons\Agent\Events\AgentFinished;
use Cognesy\Addons\Agent\Events\AgentStateUpdated;
use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Addons\Agent\Events\ContinuationEvaluated;
use Cognesy\Addons\Agent\Events\TokenUsageReported;
use Cognesy\Addons\Agent\Exceptions\AgentException;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Addons\StepByStep\Step\StepResult;
use Cognesy\Addons\StepByStep\State\Contracts\CanMarkStepStarted;
use Cognesy\Addons\StepByStep\State\Contracts\CanTrackExecutionTime;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\StepByStep\StepByStep;
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
 * @extends StepByStep<AgentState, AgentStep>
 */
class Agent extends StepByStep
{
    use HandlesEvents;

    private readonly Tools $tools;
    private readonly CanExecuteToolCalls $toolExecutor;
    private readonly CanUseTools $driver;
    private readonly ContinuationCriteria $continuationCriteria;


    /**
     * @param CanApplyProcessors<AgentState> $processors
     */
    public function __construct(
        Tools $tools,
        CanExecuteToolCalls $toolExecutor,
        CanApplyProcessors $processors,
        ContinuationCriteria $continuationCriteria,
        CanUseTools $driver,
        ?CanHandleEvents $events,
    ) {
        parent::__construct($processors);

        /** @var CanApplyProcessors<AgentState> $processors */
        $this->processors = $processors;
        $this->continuationCriteria = $continuationCriteria;
        $this->driver = $driver;
        $this->events = EventBusResolver::using($events);
        $this->tools = $tools;
        $this->toolExecutor = $toolExecutor;
    }

    // INTERNAL /////////////////////////////////////////////

    /**
     * Check if we should continue.
     * Pre-evaluates criteria before the first step, then reads from StepResult.
     */
    #[\Override]
    protected function canContinue(object $state): bool {
        assert($state instanceof AgentState);

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
    #[\Override]
    protected function makeNextStep(object $state): AgentStep {
        assert($state instanceof AgentState);
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
    #[\Override]
    protected function applyStep(object $state, object $nextStep): AgentState {
        assert($state instanceof AgentState);
        assert($nextStep instanceof AgentStep);
        $newState = $state->recordStep($nextStep);
        $this->emitAgentStateUpdated($newState);
        return $newState;
    }

    #[\Override]
    protected function onNoNextStep(object $state): AgentState {
        assert($state instanceof AgentState);
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

    #[\Override]
    protected function onStepCompleted(object $state): AgentState {
        assert($state instanceof AgentState);
        $this->emitAgentStepCompleted($state);
        return $state;
    }

    #[\Override]
    protected function onFailure(Throwable $error, object $state): AgentState {
        assert($state instanceof AgentState);
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
    #[\Override]
    protected function performStep(object $state): object {
        try {
            $stepStartedAt = microtime(true);
            $stateWithStart = $this->markStepStartedIfSupported($state);
            assert($stateWithStart instanceof AgentState);

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

    /**
     * @param CanApplyProcessors<AgentState>|null $processors
     */
    public function with(
        ?Tools $tools = null,
        ?CanExecuteToolCalls $toolExecutor = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ) : self {
        /** @var CanApplyProcessors<AgentState> $resolvedProcessors */
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

    /**
     * @param CanProcessAnyState<AgentState> ...$processors
     */
    public function withProcessors(CanProcessAnyState ...$processors): self {
        /** @var CanApplyProcessors<AgentState> $stateProcessors */
        $stateProcessors = new StateProcessors(...$processors);
        return $this->with(processors: $stateProcessors);
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

    private function markStepStartedIfSupported(object $state): object {
        if ($state instanceof CanMarkStepStarted) {
            return $state->markStepStarted();
        }
        return $state;
    }

    private function addExecutionTimeIfSupported(AgentState $state, float $seconds): AgentState {
        if ($state instanceof CanTrackExecutionTime) {
            return $state->withAddedExecutionTime($seconds);
        }
        return $state;
    }

    /**
     * Only consider state changed if a new step was added.
     * Status-only changes (e.g., InProgress → Completed) don't represent new work.
     */
    #[\Override]
    protected function isStateChanged(object $priorState, object $newState): bool {
        if (!($priorState instanceof AgentState) || !($newState instanceof AgentState)) {
            return $priorState !== $newState;
        }
        return $priorState->stepCount() !== $newState->stepCount();
    }
}
