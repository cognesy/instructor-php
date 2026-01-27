<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent;

use Cognesy\Agents\Agent\StateProcessing\CanApplyProcessors;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Agents\Agent\StateProcessing\StateProcessors;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Contracts\CanEvaluateContinuation;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Contracts\ToolInterface;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\CurrentExecution;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Core\ErrorHandling\ErrorPolicy;
use Cognesy\Agents\Core\Events\AgentEventEmitter;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Agents\Core\Lifecycle\CanObserveAgentLifecycle;
use Cognesy\Events\Contracts\CanHandleEvents;
use DateTimeImmutable;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and continuation criteria.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on the current state.
 */
class Agent implements CanControlAgentLoop
{
    private readonly AgentEventEmitter $eventEmitter;

    public function __construct(
        private readonly Tools $tools,
        private readonly CanExecuteToolCalls $toolExecutor,
        private readonly CanHandleAgentErrors $errorHandler,
        private readonly ?CanApplyProcessors $processors,
        private readonly ContinuationCriteria $continuationCriteria,
        private readonly CanUseTools $driver,
        AgentEventEmitter $eventEmitter,
        ?CanHandleEvents $events = null,
        private readonly ?CanObserveAgentLifecycle $observer = null,
    ) {
        $this->eventEmitter = $events !== null
            ? $eventEmitter->withEventHandler($events)
            : $eventEmitter;
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
            $state = $this->observer->executionStarting($state);
        }

        return $state;
    }

    protected function onBeforeStep(AgentState $state): AgentState {
        $this->eventEmitter->stepStarted($state);

        if ($this->observer !== null) {
            $state = $this->observer->stepStarting($state);
        }

        return $state;
    }

    private function onBeforeToolUse(AgentState $state) : AgentState {
        return $state;
    }

    private function onAfterToolUse(AgentState $state, AgentStep $rawStep) : AgentState
    {
        $currentExecution = $this->resolveCurrentExecution($state);
        $transitionState = $state->recordStep($rawStep);

        $outcome = $this->continuationCriteria->evaluateAll($transitionState);
        $this->eventEmitter->continuationEvaluated($transitionState, $outcome);

        $stepExecution = new StepExecution(
            step: $rawStep,
            outcome: $outcome,
            startedAt: $currentExecution->startedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $currentExecution->stepNumber,
            id: $rawStep->id(),
        );
        $nextState = $transitionState->recordStepExecution($stepExecution);
        $this->eventEmitter->stateUpdated($nextState);
        return $nextState;
    }

    protected function onAfterStep(AgentState $state): AgentState {
        if ($this->observer !== null) {
            $state = $this->observer->stepEnding($state);
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
            $finalState = $this->observer->executionEnding($finalState);
        }

        $this->eventEmitter->executionFinished($finalState);
        return $finalState;
    }

    protected function onError(Throwable $error, AgentState $state): AgentState {
        $handling = $this->errorHandler->handleError($error, $state);
        $currentExecution = $this->resolveCurrentExecution($state);
        $transitionState = $state
            ->withStatus(AgentStatus::Failed)
            ->recordStep($handling->failureStep);

        $this->eventEmitter->continuationEvaluated($transitionState, $handling->outcome);

        $stepExecution = new StepExecution(
            step: $handling->failureStep,
            outcome: $handling->outcome,
            startedAt: $currentExecution->startedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $currentExecution->stepNumber,
            id: $handling->failureStep->id(),
        );

        $nextState = $transitionState
            ->withStatus($handling->finalStatus)
            ->recordStepExecution($stepExecution);

        $this->eventEmitter->stateUpdated($nextState);
        if ($handling->finalStatus === AgentStatus::Failed) {
            $this->eventEmitter->executionFailed($nextState, $handling->exception);

            // Notify observer of failure
            if ($this->observer !== null) {
                $agentException = $handling->exception instanceof AgentException
                    ? $handling->exception
                    : AgentException::fromThrowable($handling->exception);
                $nextState = $this->observer->executionFailed($nextState, $agentException);
            }
        }

        return $nextState;
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
            $decision = $this->observer->stopping($state, $stopReason);
            if ($decision->isPrevented()) {
                return true; // Observer prevented stopping, continue execution
            }
        }

        return false;
    }

    private function performStep(AgentState $state): AgentState {
        return match(true) {
            ($this->processors === null) => $this->useTools($state),
            default => $this->processors->apply($state, fn($s) => $this->useTools($s)),
        };
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

    public function eventEmitter(): AgentEventEmitter {
        return $this->eventEmitter;
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?Tools $tools = null,
        ?CanExecuteToolCalls $toolExecutor = null,
        ?CanHandleAgentErrors $errorHandler = null,
        ?CanApplyProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?AgentEventEmitter $eventEmitter = null,
        ?CanHandleEvents $events = null,
        ?CanObserveAgentLifecycle $observer = null,
    ): self {
        $resolvedTools = $tools ?? $this->tools;

        // Resolve emitter: prefer explicit, then create new if events changed
        $resolvedEmitter = $eventEmitter ?? (
            $events !== null
                ? $this->eventEmitter->withEventHandler($events)
                : $this->eventEmitter
        );

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
            processors: $processors ?? $this->processors,
            continuationCriteria: $continuationCriteria ?? $this->continuationCriteria,
            driver: $driver ?? $this->driver,
            eventEmitter: $resolvedEmitter,
            observer: $observer ?? $this->observer,
        );
    }

    public function withProcessors(CanProcessAgentState ...$processors): self {
        return $this->with(processors: new StateProcessors(...$processors));
    }

    public function withDriver(CanUseTools $driver): self {
        return $this->with(driver: $driver);
    }

    public function withContinuationCriteria(CanEvaluateContinuation ...$criteria): self {
        return $this->with(continuationCriteria: new ContinuationCriteria(...$criteria));
    }

    public function withTools(array|ToolInterface|Tools $tools): self {
        return $this->with(tools: match (true) {
            is_array($tools) => new Tools(...$tools),
            $tools instanceof ToolInterface => new Tools($tools),
            $tools instanceof Tools => $tools,
            default => new Tools(),
        });
    }

    public function withToolExecutor(CanExecuteToolCalls $toolExecutor): self {
        return $this->with(toolExecutor: $toolExecutor);
    }

    public function withErrorHandler(CanHandleAgentErrors $errorHandler): self {
        return $this->with(errorHandler: $errorHandler);
    }

    public function withErrorPolicy(ErrorPolicy $policy): self {
        return $this->with(errorHandler: AgentErrorHandler::withPolicy($policy));
    }

    public function withObserver(?CanObserveAgentLifecycle $observer): self {
        return $this->with(observer: $observer);
    }

    public function observer(): ?CanObserveAgentLifecycle {
        return $this->observer;
    }
}
