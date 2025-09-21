<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\Core\Continuation\CanDecideToContinue;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Contracts\CanApplyProcessors;
use Cognesy\Addons\Core\Contracts\CanExecuteIteratively;
use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Addons\Core\StateProcessors;
use Cognesy\Addons\ToolUse\Collections\Tools;
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
use Generator;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and continuation criteria.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on the current state.
 * It integrates with event handling to provide feedback on the process and supports
 * state processing to modify the state after each step.
 *
 * @implements CanExecuteIteratively<ToolUseState>
 */
final readonly class ToolUse implements CanExecuteIteratively
{
    private Tools $tools;
    private ToolExecutor $toolExecutor;
    private CanUseTools $driver;
    private CanApplyProcessors $processors;
    private ContinuationCriteria $continuationCriteria;
    private CanHandleEvents $events;

    public function __construct(
        Tools $tools,
        CanApplyProcessors $processors,
        ContinuationCriteria $continuationCriteria,
        CanUseTools $driver,
        ?CanHandleEvents $events,
    ) {
        $this->processors = $processors;
        $this->continuationCriteria = $continuationCriteria;
        $this->driver = $driver;
        $this->events = EventBusResolver::using($events);
        $this->tools = $tools;
        $this->toolExecutor = (new ToolExecutor($tools))->withEventHandler($this->events);
    }

    // HANDLE PARAMETRIZATION //////////////////////////////////////

    public function driver() : CanUseTools {
        return $this->driver;
    }

    // HANDLE TOOL USE /////////////////////////////////////////////

    /**
     * @param object<ToolUseState> $state
     * @return object<ToolUseState>
     */
    public function nextStep(object $state): object {
        assert($state instanceof ToolUseState);
        if (!$this->hasNextStep($state)) {
            return $this->handleNoNextStep($state);
        }
        
        try {
            $nextStep = $this->makeNextStep($state);
        } catch (Throwable $error) {
            return $this->handleFailure($error, $state);
        }

        return $this->updateState($nextStep, $state);
    }

    /**
     * @param object<ToolUseState> $state
     */
    public function hasNextStep(object $state): bool {
        assert($state instanceof ToolUseState);
        return $this->canContinue($state);
    }

    /**
     * @param object<ToolUseState> $state
     * @return object<ToolUseState>
     */
    public function finalStep(object $state): object {
        assert($state instanceof ToolUseState);
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }
        $finalState = $this->handleNoNextStep($state);
        return match (true) {
            $finalState === $state => $state,
            default => $finalState,
        };
    }

    /**
     * @param object<ToolUseState> $state
     * @return Generator<ToolUseState>
     */
    public function iterator(object $state): iterable {
        assert($state instanceof ToolUseState);
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }

        $finalState = $this->handleNoNextStep($state);
        if ($finalState !== $state) {
            yield $finalState;
        }
    }

    // INTERNAL /////////////////////////////////////////////

    protected function handleNoNextStep(object $state) : object {
        assert($state instanceof ToolUseState);
        $this->emitToolUseFinished($state);
        return $state;
    }

    protected function canContinue(ToolUseState $state): bool {
        return $this->continuationCriteria->canContinue($state);
    }

    private function makeNextStep(ToolUseState $state) : ToolUseStep {
        $this->emitToolUseStepStarted($state);
        return $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );
    }

    private function updateState(ToolUseStep $step, ToolUseState $state, ?ToolUseStatus $status = null) : ToolUseState {
        $newState = $state
            ->withAddedStep($step)
            ->withCurrentStep($step);
        if ($status !== null) {
            $newState = $newState->withStatus($status);
        }
        $newState = $this->processors->apply($newState);
        assert($newState instanceof ToolUseState);
        $this->emitToolUseStateUpdated($newState);
        $this->emitToolUseStepCompleted($newState);
        return $newState;
    }

    private function handleFailure(Throwable $error, ToolUseState $state) : ToolUseState {
        $failure = $error instanceof ToolUseException
            ? $error
            : ToolUseException::fromThrowable($error);
        $failureStep = ToolUseStep::failure(inputMessages: $state->messages(), error: $failure);
        $failedState = $this->updateState(
            step: $failureStep,
            state: $state,
            status: ToolUseStatus::Failed,
        );
        $this->emitToolUseFailed($failedState, $failure);
        return $failedState;
    }

    // ACCESSORS ////////////////////////////////////////////

    public function tools() : Tools {
        return $this->tools;
    }

    public function toolExecutor(): ToolExecutor {
        return $this->toolExecutor;
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?Tools $tools = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ) : self {
        return new self(
            tools: $tools ?? $this->tools,
            processors: $processors ?? $this->processors,
            continuationCriteria: $continuationCriteria ?? $this->continuationCriteria,
            driver: $driver ?? $this->driver,
            events: $events ?? $this->events,
        );
    }

    public function withProcessors(CanProcessAnyState ...$processors): self {
        return $this->with(processors: new StateProcessors(...$processors));
    }

    public function withDriver(CanUseTools $driver) : self {
        return $this->with(driver: $driver);
    }

    public function withContinuationCriteria(CanDecideToContinue ...$continuationCriteria) : self {
        return $this->with(continuationCriteria: new ContinuationCriteria(...$continuationCriteria));
    }

    public function withToolExecutor(ToolExecutor $executor): self {
        $executor = $executor->withEventHandler($this->events);
        return $this->with(tools: $executor->tools());
    }

    public function withTools(array|ToolInterface|Tools $tools) : self {
        return $this->with(tools: match(true) {
            is_array($tools) => new Tools(...$tools),
            $tools instanceof ToolInterface => new Tools($tools),
            $tools instanceof Tools => $tools,
            default => new Tools(),
        });
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
