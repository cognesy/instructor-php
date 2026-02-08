<?php declare(strict_types=1);

namespace Cognesy\Agents\Core;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Stop\AgentStopException;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Agents\Exceptions\ToolExecutionBlockedException;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Interceptors\CanInterceptAgentLifecycle;
use Cognesy\Agents\Hooks\Interceptors\PassThroughInterceptor;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and stop signals.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on stop signals
 * and continuation requests emitted by hooks.
 */
class AgentLoop implements CanControlAgentLoop
{
    private CanEmitAgentEvents $eventEmitter;
    private CanInterceptAgentLifecycle $interceptor;

    public function __construct(
        private readonly Tools $tools,
        private readonly CanExecuteToolCalls $toolExecutor,
        private readonly CanUseTools $driver,
        ?CanEmitAgentEvents $eventEmitter,
        ?CanInterceptAgentLifecycle $interceptor,
    ) {
        $this->eventEmitter = $eventEmitter ?? new AgentEventEmitter();
        $this->interceptor = $interceptor ?? new PassThroughInterceptor();
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
            $stepStarted = false;
            try {
                $state = $this->onBeforeStep($state);
                $stepStarted = true;
                $state = $this->handleToolUse($state);
            } catch (AgentStopException $stop) {
                $state = $this->handleStopException($state, $stop);
            } catch (Throwable $error) {
                $state = $this->onError($state, $error);
            } finally {
                if ($stepStarted) {
                    $state = $this->onAfterStep($state);
                }
            }
            if ($this->shouldStop($state)) {
                $state = $this->onStop($state);
                $state = $state->withCurrentStepCompleted();
                break;
            }
            $state = $state->withCurrentStepCompleted();
            yield $state;
        }
        $finalState = match(true) {
            $state->hasCurrentStep() => $state->withCurrentStepCompleted(),
            default => $state,
        };
        $finalState = $this->onAfterExecution($finalState);
        if ($finalState->updatedAt() !== $state->updatedAt()) {
            yield $finalState;
        }
    }

    // LIFECYCLE HOOKS ////////////////////////////////////

    protected function onBeforeExecution(AgentState $state): AgentState {
        $this->eventEmitter->executionStarted($state, count($this->tools->names()));
        $state = $this->interceptor->intercept(HookContext::beforeExecution($state))->state();
        return $state;
    }

    protected function onBeforeStep(AgentState $state): AgentState {
        $this->eventEmitter->stepStarted($state);
        $state = $this->interceptor->intercept(HookContext::beforeStep($state))->state();
        return $state;
    }

    protected function onAfterStep(AgentState $state): AgentState {
        $state = $this->interceptor->intercept(HookContext::afterStep($state))->state();
        $this->eventEmitter->stepCompleted($state);
        return $state;
    }

    private function onStop(AgentState $state) : AgentState {
        $state = $this->interceptor->intercept(HookContext::onStop($state))->state();
        $this->eventEmitter->executionStopped($state);
        return $state;
    }

    protected function onAfterExecution(AgentState $state): AgentState {
        $state = $this->interceptor->intercept(HookContext::afterExecution($state))->state();
        $this->eventEmitter->executionFinished($state);
        $state = $state->withExecutionCompleted();
        return $state;
    }

    protected function onError(AgentState $state, Throwable $error): AgentState {
        $state = $state->withFailure(AgentException::fromError($error));
        $state = $this->interceptor->intercept(HookContext::onError($state, $state->errors()))->state();
        $this->eventEmitter->executionFailed($state, $error);
        return $state;
    }

    // INTERNAL ///////////////////////////////////////////

    private function handleToolUse(AgentState $state) : AgentState {
        try {
            $state = $this->useTools($state);
        } catch (ToolExecutionBlockedException $block) {
            $state = $this->handleToolBlockException($state, $block);
        }
        return $state;
    }

    private function handleToolBlockException(AgentState $state, ToolExecutionBlockedException $stop) : AgentState {
        $this->eventEmitter->toolCallBlocked($stop->toolCall, $stop->getMessage(), $stop->hookName);
        return $this->onError($state, $stop);
    }

    private function handleStopException(AgentState $state, AgentStopException $stop): AgentState {
        $signal = StopSignal::fromStopException($stop);
        $this->eventEmitter->stopSignalReceived($signal);
        return $state->withStopSignal($signal);
    }

    protected function shouldStop(AgentState $state): bool {
        $shouldStop = match(true) {
            $state->shouldStop() => true,
            default => false,
        };
        $this->eventEmitter->continuationEvaluated($state);
        return $shouldStop;
    }

    private function useTools(AgentState $state): AgentState {
        $state = $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this->toolExecutor
        );
        return $state;
    }

    // EVENT DELEGATION ///////////////////////////////////

    public function wiretap(callable $listener): self {
        $this->eventEmitter->wiretap($listener);
        return $this;
    }

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

    public function driver(): CanUseTools {
        return $this->driver;
    }

    public function eventEmitter(): CanEmitAgentEvents {
        return $this->eventEmitter;
    }

    public function interceptor(): ?CanInterceptAgentLifecycle {
        return $this->interceptor;
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?Tools $tools = null,
        ?CanExecuteToolCalls $toolExecutor = null,
        ?CanUseTools $driver = null,
        ?CanEmitAgentEvents $eventEmitter = null,
        ?CanInterceptAgentLifecycle $interceptor = null,
    ): self {
        $resolvedTools = $tools ?? $this->tools;
        $resolvedEmitter = $eventEmitter ?? $this->eventEmitter;
        $resolvedInterceptor = $interceptor ?? $this->interceptor;

        $resolvedExecutor = match (true) {
            $toolExecutor !== null => $toolExecutor,
            $tools !== null => new ToolExecutor($resolvedTools, $resolvedEmitter, $resolvedInterceptor),
            default => $this->toolExecutor,
        };

        return new self(
            tools: $resolvedTools,
            toolExecutor: $resolvedExecutor,
            driver: $driver ?? $this->driver,
            eventEmitter: $resolvedEmitter,
            interceptor: $resolvedInterceptor,
        );
    }
}
