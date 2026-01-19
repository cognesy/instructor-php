<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\StepByStep\Step\StepResult;
use Cognesy\Addons\StepByStep\StepByStep;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\Events\ToolUseFinished;
use Cognesy\Addons\ToolUse\Events\ToolUseStateUpdated;
use Cognesy\Addons\ToolUse\Events\ToolUseStepCompleted;
use Cognesy\Addons\ToolUse\Events\ToolUseStepStarted;
use Cognesy\Addons\ToolUse\Exceptions\ToolUseException;
use Cognesy\Addons\ToolUse\Exceptions\ToolUseFailed;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and continuation criteria.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on the current state.
 * It integrates with event handling to provide feedback on the process and supports
 * state processing to modify the state after each step.
 *
 * @extends StepByStep<ToolUseState, ToolUseStep>
 */
class ToolUse extends StepByStep
{
    use HandlesEvents;

    private readonly Tools $tools;
    private readonly CanExecuteToolCalls $toolExecutor;
    private readonly CanUseTools $driver;
    private readonly ContinuationCriteria $continuationCriteria;

    /**
     * @param CanApplyProcessors<ToolUseState> $processors
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

        /** @var CanApplyProcessors<ToolUseState> $processors */
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
        assert($state instanceof ToolUseState);

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

    #[\Override]
    protected function makeNextStep(object $state): ToolUseStep {
        assert($state instanceof ToolUseState);
        $this->emitToolUseStepStarted($state);
        return $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );
    }

    #[\Override]
    protected function applyStep(object $state, object $nextStep): ToolUseState {
        assert($state instanceof ToolUseState);
        assert($nextStep instanceof ToolUseStep);
        $newState = $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep);
        $this->emitToolUseStateUpdated($newState);
        return $newState;
    }

    #[\Override]
    protected function onNoNextStep(object $state): ToolUseState {
        assert($state instanceof ToolUseState);
        $this->emitToolUseFinished($state);
        return $state;
    }

    #[\Override]
    protected function onStepCompleted(object $state): ToolUseState {
        assert($state instanceof ToolUseState);
        $this->emitToolUseStepCompleted($state);
        return $state;
    }

    #[\Override]
    protected function onFailure(Throwable $error, object $state): ToolUseState {
        assert($state instanceof ToolUseState);
        $failure = $error instanceof ToolUseException
            ? $error
            : ToolUseException::fromThrowable($error);
        $failureStep = ToolUseStep::failure(
            inputMessages: $state->messages(),
            error: $failure,
        );
        $transitionState = $state
            ->withStatus(ToolUseStatus::Failed)
            ->withAddedStep($failureStep)
            ->withCurrentStep($failureStep)
            ->withAccumulatedUsage($failureStep->usage());
        $outcome = $this->evaluateOutcomeOnFailure($transitionState);
        $stepResult = new StepResult($failureStep, $outcome);
        $failedState = $state
            ->withStatus(ToolUseStatus::Failed)
            ->recordStepResult($stepResult)
            ->withAccumulatedUsage($failureStep->usage());
        $this->emitToolUseStateUpdated($failedState);
        $this->emitToolUseFailed($failedState, $failure);
        return $failedState;
    }

    /**
     * Perform a single step: create step, evaluate continuation, bundle into StepResult, record.
     */
    #[\Override]
    protected function performStep(object $state): object {
        try {
            assert($state instanceof ToolUseState);

            // 1. Create raw step from driver
            $rawStep = $this->makeNextStep($state);

            // 2. Create transition state with step recorded (for correct stepCount during evaluation)
            // Also accumulate usage so TokenUsageLimit can evaluate correctly
            $transitionState = $state
                ->withAddedStep($rawStep)
                ->withCurrentStep($rawStep)
                ->withAccumulatedUsage($rawStep->usage());

            // 3. Evaluate continuation criteria on state with this step
            $outcome = $this->continuationCriteria->evaluateAll($transitionState);

            // 4. Bundle step + outcome into StepResult
            $stepResult = new StepResult($rawStep, $outcome);

            // 5. Record the StepResult to the original state
            $nextState = $state->recordStepResult($stepResult);
            $this->emitToolUseStateUpdated($nextState);

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

    private function evaluateOutcomeOnFailure(ToolUseState $state): ContinuationOutcome {
        try {
            return $this->continuationCriteria->evaluateAll($state);
        } catch (Throwable $error) {
            return ContinuationOutcome::fromEvaluationError($error);
        }
    }

    // MUTATORS /////////////////////////////////////////////

    /**
     * @param CanApplyProcessors<ToolUseState>|null $processors
     */
    public function with(
        ?Tools $tools = null,
        ?CanExecuteToolCalls $toolExecutor = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ) : self {
        return new self(
            tools: $tools ?? $this->tools,
            toolExecutor: $toolExecutor ?? $this->toolExecutor,
            processors: $processors ?? $this->processors,
            continuationCriteria: $continuationCriteria ?? $this->continuationCriteria,
            driver: $driver ?? $this->driver,
            events: $events ?? $this->events,
        );
    }

    /**
     * @param CanProcessAnyState<ToolUseState> ...$processors
     */
    public function withProcessors(CanProcessAnyState ...$processors): self {
        /** @var CanApplyProcessors<ToolUseState> $stateProcessors */
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

    private function emitToolUseFinished(ToolUseState $state) : void {
        $this->events->dispatch(new ToolUseFinished([
            'status' => $state->status()->value,
            'steps' => $state->stepCount(),
            'usage' => $state->usage()->toArray(),
            'errors' => $state->currentStep()?->errorsAsString(),
        ]));
    }

    private function emitToolUseStepStarted(ToolUseState $state) : void {
        $this->events->dispatch(new ToolUseStepStarted([
            'step' => $state->stepCount() + 1,
            'messages' => $state->messages()->count(),
            'tools' => count($this->tools->names()),
        ]));
    }

    private function emitToolUseStepCompleted(ToolUseState $state) : void {
        $this->events->dispatch(new ToolUseStepCompleted([
            'step' => $state->stepCount(),
            'hasToolCalls' => $state->currentStep()?->hasToolCalls() ?? false,
            'errors' => count($state->currentStep()?->errors() ?? []),
            'errorMessages' => $state->currentStep()?->errorsAsString() ?? '',
            'usage' => $state->currentStep()?->usage()->toArray() ?? [],
            'finishReason' => $state->currentStep()?->finishReason()?->value ?? null,
        ]));
    }

    private function emitToolUseStateUpdated(ToolUseState $state) : void {
        $this->events->dispatch(new ToolUseStateUpdated([
            'state' => $state->toArray(),
            'step' => $state->currentStep()?->toArray() ?? [],
        ]));
    }

    private function emitToolUseFailed(ToolUseState $failedState, ToolUseException $exception) : void {
        $this->events->dispatch(new ToolUseFailed([
            'error' => $exception->getMessage(),
            'status' => $failedState->status()->value,
            'steps' => $failedState->stepCount(),
            'usage' => $failedState->usage()->toArray(),
            'errors' => $failedState->currentStep()?->errorsAsString(),
        ]));
    }
}
