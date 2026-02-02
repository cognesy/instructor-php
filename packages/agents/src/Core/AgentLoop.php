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
use Cognesy\Agents\Hooks\CanInterceptAgentLifecycle;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\PassThroughInterceptor;
use Throwable;
use tmp\ErrorHandling\Contracts\CanHandleAgentErrors;

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
        private readonly CanHandleAgentErrors $errorHandler,
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
            try {
                try {
                    $state = $this->onBeforeStep($state);
                    $state = $this->useTools($state);
                } catch (Throwable $error) {
                    $state = $this->onError($error, $state);
                    $state = $state->withFailure($error);
                }
                $state = $state->withCurrentStepCompleted();
                $state = $this->onAfterStep($state);
            } catch (AgentStopException $stop) {
                $state = $this->handleStopException($stop, $state);
                $state = $state->withCurrentStepCompleted();
            } catch (ToolExecutionBlockedException $block) {
                $state = $state->withFailure(AgentException::fromError($block));
            }
            if ($this->shouldStop($state)) {
                break;
            }
            yield $state;
        }
        $finalState = $this->onAfterExecution($state);
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

    protected function onAfterExecution(AgentState $state): AgentState {
        $state = $state->withExecutionCompleted();
        $state = $this->interceptor->intercept(HookContext::afterExecution($state))->state();
        $this->eventEmitter->executionFinished($state);
        return $state;
    }

    protected function onError(Throwable $error, AgentState $state): AgentState {
        $state = $state->withFailure(AgentException::fromError($error));
        $state = $this->interceptor->intercept(HookContext::onError($state, $state->errors()))->state();
        $this->eventEmitter->executionFailed($state, $error);
        return $state;
    }

    // INTERNAL ///////////////////////////////////////////

    private function handleStopException(AgentStopException $stop, AgentState $state): AgentState {
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

    public function errorHandler(): CanHandleAgentErrors {
        return $this->errorHandler;
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
        ?CanHandleAgentErrors $errorHandler = null,
        ?CanUseTools $driver = null,
        ?CanEmitAgentEvents $eventEmitter = null,
        ?CanInterceptAgentLifecycle $interceptor = null,
    ): self {
        $resolvedTools = $tools ?? $this->tools;
        // If tools changed but no executor provided, create new executor
        $resolvedExecutor = $toolExecutor ?? (
            $tools !== null
                ? new ToolExecutor($resolvedTools, $this->eventEmitter, $this->interceptor)
                : $this->toolExecutor
        );
        return new self(
            tools: $resolvedTools,
            toolExecutor: $resolvedExecutor,
            errorHandler: $errorHandler ?? $this->errorHandler,
            driver: $driver ?? $this->driver,
            eventEmitter: $eventEmitter ?? $this->eventEmitter,
            interceptor: $interceptor ?? $this->interceptor,
        );
    }
}
