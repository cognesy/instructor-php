<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Core\Tools\ToolExecutor;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Exceptions\ToolExecutionBlockedException;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Enums\HookTrigger;
use Cognesy\Agents\Hooks\Interceptors\CanInterceptAgentLifecycle;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use RuntimeException;

final class CountingEventListener
{
    public int $stepStartedCount = 0;
    public int $stepCompletedCount = 0;
    public int $executionFailedCount = 0;
    public int $toolCallBlockedCount = 0;
    public ?ExecutionStatus $finishedStatus = null;

    public function wiretap(): callable
    {
        return function (object $event): void {
            match (true) {
                $event instanceof AgentStepStarted => $this->stepStartedCount++,
                $event instanceof AgentStepCompleted => $this->stepCompletedCount++,
                $event instanceof AgentExecutionFailed => $this->executionFailedCount++,
                $event instanceof ToolCallBlocked => $this->toolCallBlockedCount++,
                $event instanceof AgentExecutionCompleted => $this->finishedStatus = $event->status,
                default => null,
            };
        };
    }
}

final class ThrowingInterceptor implements CanInterceptAgentLifecycle
{
    public function __construct(private HookTrigger $trigger) {}

    public function intercept(HookContext $context): HookContext
    {
        if ($context->triggerType()->equals($this->trigger)) {
            throw new RuntimeException('intercept boom');
        }

        return $context;
    }
}

final class InjectStepInterceptor implements CanInterceptAgentLifecycle
{
    public function __construct(private AgentStep $step) {}

    public function intercept(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeStep => $context->withState(
                $context->state()->withCurrentStep($this->step),
            ),
            default => $context,
        };
    }
}

function makeTestEvents(CountingEventListener $listener): EventDispatcher
{
    $events = new EventDispatcher();
    $events->wiretap($listener->wiretap());
    return $events;
}

describe('AgentLoop flow', function () {
    it('skips after-step when before-step hook throws', function () {
        $tools = new Tools();
        $listener = new CountingEventListener();
        $events = makeTestEvents($listener);
        $interceptor = new ThrowingInterceptor(HookTrigger::BeforeStep);

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                return $state;
            }
        };

        $loop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $events, $interceptor),
            driver: $driver,
            events: $events,
            interceptor: $interceptor,
        );

        $finalState = $loop->execute(AgentState::empty());

        expect($listener->stepStartedCount)->toBe(1);
        expect($listener->stepCompletedCount)->toBe(0);
        expect($finalState->stepCount())->toBe(0);
        expect($finalState->status())->toBe(ExecutionStatus::Failed);
    });

    it('records a failed step when tool use throws after a step is prepared', function () {
        $tools = new Tools();
        $listener = new CountingEventListener();
        $events = makeTestEvents($listener);
        $step = new AgentStep();
        $interceptor = new InjectStepInterceptor($step);

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                throw new RuntimeException('driver boom');
            }
        };

        $loop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $events, $interceptor),
            driver: $driver,
            events: $events,
            interceptor: $interceptor,
        );

        $finalState = $loop->execute(AgentState::empty());

        expect($finalState->status())->toBe(ExecutionStatus::Failed);
        expect($finalState->stepCount())->toBe(1);
        expect($finalState->errors()->hasAny())->toBeTrue();
        expect($listener->stepCompletedCount)->toBe(1);
    });

    it('emits tool-block and failure events when tool execution is blocked', function () {
        $tools = new Tools();
        $listener = new CountingEventListener();
        $events = makeTestEvents($listener);
        $step = new AgentStep();
        $interceptor = new InjectStepInterceptor($step);

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                $toolCall = ToolCall::fromArray([
                    'id' => 'call_blocked',
                    'name' => 'test_tool',
                    'arguments' => json_encode(['arg' => 'val']),
                ]);

                throw new ToolExecutionBlockedException($toolCall, 'blocked', 'TestHook');
            }
        };

        $loop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $events, $interceptor),
            driver: $driver,
            events: $events,
            interceptor: $interceptor,
        );

        $finalState = $loop->execute(AgentState::empty());

        expect($finalState->status())->toBe(ExecutionStatus::Failed);
        expect($listener->toolCallBlockedCount)->toBe(1);
        expect($listener->executionFailedCount)->toBe(1);
        expect($listener->stepCompletedCount)->toBe(1);
    });

    it('uses new events in executor when with() receives both tools and events', function () {
        $tools = new Tools();
        $oldListener = new CountingEventListener();
        $newListener = new CountingEventListener();
        $oldEvents = makeTestEvents($oldListener);
        $newEvents = makeTestEvents($newListener);
        $step = new AgentStep();
        $interceptor = new InjectStepInterceptor($step);

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                return $state;
            }
        };

        $originalLoop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $oldEvents, $interceptor),
            driver: $driver,
            events: $oldEvents,
            interceptor: $interceptor,
        );

        $newTools = new Tools();
        $newLoop = $originalLoop->with(tools: $newTools, events: $newEvents);

        $newLoop->execute(AgentState::empty());

        expect($newListener->stepStartedCount)->toBe(1)
            ->and($newListener->stepCompletedCount)->toBe(1)
            ->and($oldListener->stepStartedCount)->toBe(0)
            ->and($oldListener->stepCompletedCount)->toBe(0);
    });

    it('emits executionFinished with Completed status, not InProgress', function () {
        $tools = new Tools();
        $listener = new CountingEventListener();
        $events = makeTestEvents($listener);
        $interceptor = new InjectStepInterceptor(new AgentStep());

        // Driver that returns immediately (no tool calls) — simulates LLM finish=stop
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState
            {
                return $state;
            }
        };

        $loop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $events, $interceptor),
            driver: $driver,
            events: $events,
            interceptor: $interceptor,
        );

        $finalState = $loop->execute(AgentState::empty());

        // The event must carry the final status, not the transient in-progress status
        expect($listener->finishedStatus)->toBe(ExecutionStatus::Completed);
        expect($finalState->status())->toBe(ExecutionStatus::Completed);
    });
});
