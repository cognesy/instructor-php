<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent;

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Contracts\ToolInterface;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Events\AgentFailed;
use Cognesy\Addons\Agent\Events\AgentFinished;
use Cognesy\Addons\Agent\Events\AgentStateUpdated;
use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Addons\Agent\Exceptions\AgentException;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\StateProcessing\CanApplyProcessors;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\StepByStep\StepByStep;
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

    #[\Override]
    protected function canContinue(object $state): bool {
        assert($state instanceof AgentState);
        return $this->continuationCriteria->canContinue($state);
    }

    #[\Override]
    protected function makeNextStep(object $state): AgentStep {
        assert($state instanceof AgentState);
        $this->emitToolUseStepStarted($state);
        return $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );
    }

    #[\Override]
    protected function applyStep(object $state, object $nextStep): AgentState {
        assert($state instanceof AgentState);
        assert($nextStep instanceof AgentStep);
        $newState = $state
            ->withAddedStep($nextStep)
            ->withCurrentStep($nextStep);
        $this->emitToolUseStateUpdated($newState);
        return $newState;
    }

    #[\Override]
    protected function onNoNextStep(object $state): AgentState {
        assert($state instanceof AgentState);
        $this->emitToolUseFinished($state);
        return $state;
    }

    #[\Override]
    protected function onStepCompleted(object $state): AgentState {
        assert($state instanceof AgentState);
        $this->emitToolUseStepCompleted($state);
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
        $failedState = $this->applyStep(
            state: $state->withStatus(AgentStatus::Failed),
            nextStep: $failureStep,
        );
        $this->emitToolUseFailed($failedState, $failure);
        return $failedState;
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

    public function withContinuationCriteria(CanDecideToContinue ...$continuationCriteria) : self {
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

    private function emitToolUseFinished(AgentState $state) : void {
        $this->events->dispatch(new AgentFinished([
            'status' => $state->status()->value,
            'steps' => $state->stepCount(),
            'usage' => $state->usage()->toArray(),
            'errors' => $state->currentStep()?->errorsAsString(),
        ]));
    }

    private function emitToolUseStepStarted(AgentState $state) : void {
        $this->events->dispatch(new AgentStepStarted([
            'step' => $state->stepCount() + 1,
            'messages' => $state->messages()->count(),
            'tools' => count($this->tools->names()),
        ]));
    }

    private function emitToolUseStepCompleted(AgentState $state) : void {
        $this->events->dispatch(new AgentStepCompleted([
            'step' => $state->stepCount(),
            'hasToolCalls' => $state->currentStep()?->hasToolCalls() ?? false,
            'errors' => count($state->currentStep()?->errors() ?? []),
            'errorMessages' => $state->currentStep()?->errorsAsString() ?? '',
            'usage' => $state->currentStep()?->usage()->toArray() ?? [],
            'finishReason' => $state->currentStep()?->finishReason()?->value ?? null,
        ]));
    }

    private function emitToolUseStateUpdated(AgentState $state) : void {
        $this->events->dispatch(new AgentStateUpdated([
            'state' => $state->toArray(),
            'step' => $state->currentStep()?->toArray() ?? [],
        ]));
    }

    private function emitToolUseFailed(AgentState $failedState, AgentException $exception) : void {
        $this->events->dispatch(new AgentFailed([
            'error' => $exception->getMessage(),
            'status' => $failedState->status()->value,
            'steps' => $failedState->stepCount(),
            'usage' => $failedState->usage()->toArray(),
            'errors' => $failedState->currentStep()?->errorsAsString(),
        ]));
    }
}
