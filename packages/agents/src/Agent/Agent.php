<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent;

use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Contracts\ToolInterface;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Data\StepResult;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Agents\Agent\ErrorHandling\AgentErrorHandler;
use Cognesy\Agents\Agent\ErrorHandling\ErrorPolicy;
use Cognesy\Agents\Agent\Events\AgentEventEmitter;
use Cognesy\Agents\Agent\StateProcessing\CanApplyProcessors;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Agents\Agent\StateProcessing\StateProcessors;
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
                if (!$this->hasNextStep($state)) {
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
        $state = $state->markExecutionStarted();
        $this->eventEmitter->executionStarted($state, count($this->tools->names()));
        return $state;
    }

    protected function onBeforeStep(AgentState $state): AgentState {
        $state = $state->withCurrentStepStartedAt(new DateTimeImmutable());
        $this->eventEmitter->stepStarted($state);
        return $state;
    }

    private function onBeforeToolUse(AgentState $state) : AgentState {
        return $state;
    }

    private function onAfterToolUse(AgentState $state, AgentStep $rawStep) : AgentState
    {
        $transitionState = $state
            ->recordStep($rawStep)
            ->withAccumulatedUsage($rawStep->usage());

        $outcome = $this->continuationCriteria->evaluateAll($transitionState);
        $this->eventEmitter->continuationEvaluated($transitionState, $outcome);

        $stepResult = new StepResult(
            step: $rawStep,
            outcome: $outcome,
            startedAt: $state->currentStepStartedAt ?? new DateTimeImmutable(),
            completedAt: new DateTimeImmutable(),
        );
        $nextState = $state->recordStepResult($stepResult);
        $this->eventEmitter->stateUpdated($nextState);
        return $nextState;
    }

    protected function onAfterStep(AgentState $state): AgentState {
        $this->eventEmitter->stepCompleted($state);
        return $state;
    }

    protected function onAfterExecution(AgentState $state): AgentState {
        $status = $state->stopReason() === StopReason::ErrorForbade
            ? AgentStatus::Failed
            : AgentStatus::Completed;

        $finalState = $state->withStatus($status);
        $this->eventEmitter->executionFinished($finalState);
        return $finalState;
    }

    protected function onError(Throwable $error, AgentState $state): AgentState {
        return $this->errorHandler->handleError($error, $state);
    }

    // INTERNAL ///////////////////////////////////////////

    protected function hasNextStep(AgentState $state): bool {
        if ($state->status() === AgentStatus::Failed) {
            return false;
        }
        if ($state->stepCount() === 0) {
            return $this->continuationCriteria->canContinue($state);
        }
        return $state->stepResults()->shouldContinue();
    }

    private function performStep(AgentState $state): AgentState {
        return match(true) {
            ($this->processors === null) => $this->executeStep($state),
            default => $this->processors->apply($state, fn($s) => $this->executeStep($s)),
        };
    }

    private function executeStep(AgentState $state): AgentState {
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
}
