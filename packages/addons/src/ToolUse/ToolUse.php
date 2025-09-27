<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\StepByStep\StepByStep;
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
    private readonly ToolExecutor $toolExecutor;
    private readonly CanUseTools $driver;
    private readonly ContinuationCriteria $continuationCriteria;

    public function __construct(
        Tools $tools,
        CanApplyProcessors $processors,
        ContinuationCriteria $continuationCriteria,
        CanUseTools $driver,
        ?CanHandleEvents $events,
    ) {
        parent::__construct($processors);
        
        $this->processors = $processors;
        $this->continuationCriteria = $continuationCriteria;
        $this->driver = $driver;
        $this->events = EventBusResolver::using($events);
        $this->tools = $tools;
        $this->toolExecutor = (new ToolExecutor($tools))->withEventHandler($this->events);
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(object $state): bool {
        assert($state instanceof ToolUseState);
        return $this->continuationCriteria->canContinue($state);
    }

    protected function makeNextStep(object $state): ToolUseStep {
        assert($state instanceof ToolUseState);
        $this->emitToolUseStepStarted($state);
        return $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );
    }

    protected function applyStep(object $state, object $nextStep): ToolUseState {
        assert($state instanceof ToolUseState);
        assert($nextStep instanceof ToolUseStep);
        $newState = $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep);
        $this->emitToolUseStateUpdated($newState);
        return $newState;
    }

    protected function onNoNextStep(object $state): ToolUseState {
        assert($state instanceof ToolUseState);
        $this->emitToolUseFinished($state);
        return $state;
    }

    protected function onStepCompleted(object $state): ToolUseState {
        assert($state instanceof ToolUseState);
        $this->emitToolUseStepCompleted($state);
        return $state;
    }

    protected function onFailure(Throwable $error, object $state): ToolUseState {
        assert($state instanceof ToolUseState);
        $failure = $error instanceof ToolUseException
            ? $error
            : ToolUseException::fromThrowable($error);
        $failureStep = ToolUseStep::failure(
            inputMessages: $state->messages(),
            error: $failure,
        );
        $failedState = $this->applyStep(
            state: $state->withStatus(ToolUseStatus::Failed),
            nextStep: $failureStep,
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

    public function driver() : CanUseTools {
        return $this->driver;
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
