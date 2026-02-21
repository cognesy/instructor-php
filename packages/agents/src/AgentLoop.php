<?php declare(strict_types=1);

namespace Cognesy\Agents;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentExecutionStopped;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Agents\Events\StopSignalReceived;
use Cognesy\Agents\Events\TokenUsageReported;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Agents\Exceptions\ToolExecutionBlockedException;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Config\ConfigResolver;
use Cognesy\Events\Contracts\CanAcceptEventHandler;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Throwable;

/**
 * Orchestrates the iterative use of tools based on a given state and stop signals.
 *
 * This class manages the process of using tools in a sequence of steps, allowing for
 * dynamic decision-making on whether to continue or stop based on stop signals
 * and continuation requests emitted by hooks.
 */
readonly class AgentLoop implements CanControlAgentLoop, CanAcceptEventHandler
{
    public function __construct(
        private Tools $tools,
        private CanExecuteToolCalls $toolExecutor,
        private CanUseTools $driver,
        private CanHandleEvents $events,
        private CanInterceptAgentLifecycle $interceptor,
    ) {}

    public static function default() : self {
        $events = new EventDispatcher('agent-loop');
        $interceptor = new PassThroughInterceptor();
        $tools = new Tools();
        $llm = LLMProvider::new(ConfigResolver::default());
        return new self(
            tools: $tools,
            toolExecutor: new ToolExecutor(
                tools: $tools,
                events: $events,
                interceptor: $interceptor,
            ),
            driver: new ToolCallingDriver(
                llm: $llm,
                inference: InferenceRuntime::fromProvider($llm, events: $events),
                messageCompiler: new ConversationWithCurrentToolTrace(),
                events: $events,
            ),
            events: $events,
            interceptor: $interceptor,
        );
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
        $state = $this->ensureNextExecution($state);
        $state = $state->with(executionCount: $state->executionCount() + 1);
        $this->emitExecutionStarted($state, count($this->tools->names()));
        $state = $this->interceptor->intercept(HookContext::beforeExecution($state))->state();
        return $state;
    }

    protected function onBeforeStep(AgentState $state): AgentState {
        $this->emitStepStarted($state);
        $state = $this->interceptor->intercept(HookContext::beforeStep($state))->state();
        return $state;
    }

    protected function onAfterStep(AgentState $state): AgentState {
        $state = $this->interceptor->intercept(HookContext::afterStep($state))->state();
        $this->emitStepCompleted($state);
        return $state;
    }

    private function onStop(AgentState $state) : AgentState {
        $state = $this->interceptor->intercept(HookContext::onStop($state))->state();
        $this->emitExecutionStopped($state);
        return $state;
    }

    protected function onAfterExecution(AgentState $state): AgentState {
        $state = $this->interceptor->intercept(HookContext::afterExecution($state))->state();
        $state = $state->withExecutionCompleted();
        $this->emitExecutionFinished($state);
        return $state;
    }

    protected function onError(AgentState $state, Throwable $error): AgentState {
        $state = $state->withFailure(AgentException::fromError($error));
        $state = $this->interceptor->intercept(HookContext::onError($state, $state->errors()))->state();
        $this->emitExecutionFailed($state, $error);
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
        $this->emitToolCallBlocked($stop->toolCall, $stop->getMessage(), $stop->hookName);
        return $this->onError($state, $stop);
    }

    private function handleStopException(AgentState $state, AgentStopException $stop): AgentState {
        $signal = StopSignal::fromStopException($stop);
        $this->emitStopSignalReceived($signal);
        return $state->withStopSignal($signal);
    }

    private function ensureNextExecution(AgentState $state): AgentState {
        return match ($state->status()) {
            ExecutionStatus::Completed, ExecutionStatus::Stopped, ExecutionStatus::Failed => $state->forNextExecution(),
            default => $state,
        };
    }

    protected function shouldStop(AgentState $state): bool {
        $shouldStop = match(true) {
            $state->shouldStop() => true,
            default => false,
        };
        $this->emitContinuationEvaluated($state);
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
        $this->events->wiretap($listener);
        return $this;
    }

    public function onEvent(string $eventClass, callable $listener): self {
        $this->events->addListener($eventClass, $listener);
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

    public function eventHandler(): CanHandleEvents {
        return $this->events;
    }

    public function interceptor(): ?CanInterceptAgentLifecycle {
        return $this->interceptor;
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?Tools $tools = null,
        ?CanExecuteToolCalls $toolExecutor = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
        ?CanInterceptAgentLifecycle $interceptor = null,
    ): self {
        $resolvedTools = $tools ?? $this->tools;
        $resolvedEvents = $events ?? $this->events;
        $resolvedInterceptor = $interceptor ?? $this->interceptor;

        $resolvedExecutor = match (true) {
            $toolExecutor !== null => $toolExecutor,
            $tools !== null || $events !== null || $interceptor !== null
                => new ToolExecutor($resolvedTools, $resolvedEvents, $resolvedInterceptor),
            default => $this->toolExecutor,
        };

        return new self(
            tools: $resolvedTools,
            toolExecutor: $resolvedExecutor,
            driver: $driver ?? $this->driver,
            events: $resolvedEvents,
            interceptor: $resolvedInterceptor,
        );
    }

    public function withTools(Tools $tools): self {
        return $this->with(tools: $tools);
    }

    public function withTool(ToolInterface $tool): self {
        return $this->withTools($this->tools->withTool($tool));
    }

    public function withToolExecutor(CanExecuteToolCalls $executor): self {
        return $this->with(toolExecutor: $executor);
    }

    public function withDriver(CanUseTools $driver): self {
        return $this->with(driver: $driver);
    }

    public function withInterceptor(CanInterceptAgentLifecycle $interceptor): self {
        return $this->with(interceptor: $interceptor);
    }

    public function withEventHandler(CanHandleEvents $events): static {
        return $this->with(events: $events);
    }

    // EVENT EMISSION ////////////////////////////////////////

    private function emitExecutionStarted(AgentState $state, int $availableTools): void {
        $this->events->dispatch(new AgentExecutionStarted(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            messageCount: $state->messages()->count(),
            availableTools: $availableTools,
        ));
    }

    private function emitStepStarted(AgentState $state): void {
        $this->events->dispatch(new AgentStepStarted(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            stepNumber: $state->stepCount() + 1,
            messageCount: $state->messages()->count(),
            availableTools: 0,
        ));
    }

    private function emitStepCompleted(AgentState $state): void {
        $usage = $state->currentStep()?->usage() ?? new Usage(0, 0);
        $durationMs = ($state->currentStepDuration() ?? 0.0) * 1000;

        $this->events->dispatch(new AgentStepCompleted(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            stepNumber: $state->stepCount(),
            hasToolCalls: $state->currentStep()?->hasToolCalls() ?? false,
            errorCount: $state->currentStep()?->errors()?->count() ?? 0,
            errorMessages: $state->currentStep()?->errorsAsString() ?? '',
            usage: $usage,
            finishReason: $state->currentStep()?->finishReason(),
            startedAt: new \DateTimeImmutable(),
            durationMs: $durationMs,
        ));

        if ($usage->total() > 0) {
            $this->events->dispatch(new TokenUsageReported(
                agentId: $state->agentId()->toString(),
                parentAgentId: $state->parentAgentId()?->toString(),
                operation: 'step',
                usage: $usage,
                context: [
                    'step' => $state->stepCount(),
                    'hasToolCalls' => $state->currentStep()?->hasToolCalls() ?? false,
                ],
            ));
        }
    }

    private function emitExecutionStopped(AgentState $state): void {
        $signal = $state->executionContinuation()?->stopSignals()->first();
        $this->events->dispatch(new AgentExecutionStopped(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            stopReason: $signal?->reason ?? StopReason::Unknown,
            stopMessage: $signal?->message ?? '',
            source: $signal?->source,
            totalSteps: $state->stepCount(),
        ));
    }

    private function emitExecutionFinished(AgentState $state): void {
        $this->events->dispatch(new AgentExecutionCompleted(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            status: $state->status(),
            totalSteps: $state->stepCount(),
            totalUsage: $state->usage(),
            errors: $state->currentStep()?->errorsAsString(),
        ));
    }

    private function emitExecutionFailed(AgentState $state, Throwable $exception): void {
        $this->events->dispatch(new AgentExecutionFailed(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            exception: $exception,
            status: $state->status(),
            stepsCompleted: $state->stepCount(),
            totalUsage: $state->usage(),
            errors: $state->currentStep()?->errorsAsString(),
        ));
    }

    private function emitContinuationEvaluated(AgentState $state): void {
        $this->events->dispatch(new ContinuationEvaluated(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            stepNumber: $state->stepCount(),
            executionState: $state->execution(),
        ));
    }

    private function emitToolCallBlocked(ToolCall $toolCall, string $reason, ?string $hookName): void {
        $this->events->dispatch(new ToolCallBlocked(
            tool: $toolCall->name(),
            args: $toolCall->args(),
            reason: $reason,
            hookName: $hookName,
        ));
    }

    private function emitStopSignalReceived(StopSignal $signal): void {
        $this->events->dispatch(new StopSignalReceived(
            reason: $signal->reason,
            message: $signal->message,
            context: $signal->context,
            source: $signal->source,
        ));
    }
}
