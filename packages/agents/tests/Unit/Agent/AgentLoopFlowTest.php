<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStopped;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Agents\Exceptions\ToolExecutionBlockedException;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;
use Cognesy\Agents\Interception\PassThroughInterceptor;
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolCall;
use RuntimeException;

final class CountingEventListener
{
    public int $stepStartedCount = 0;
    public int $stepCompletedCount = 0;
    public int $executionFailedCount = 0;
    public int $toolCallBlockedCount = 0;
    public int $toolCallStartedCount = 0;
    public int $toolCallCompletedCount = 0;
    public ?ExecutionStatus $finishedStatus = null;

    public function wiretap(): callable
    {
        return function (object $event): void {
            match (true) {
                $event instanceof AgentStepStarted => $this->stepStartedCount++,
                $event instanceof AgentStepCompleted => $this->stepCompletedCount++,
                $event instanceof AgentExecutionFailed => $this->executionFailedCount++,
                $event instanceof ToolCallBlocked => $this->toolCallBlockedCount++,
                $event instanceof ToolCallStarted => $this->toolCallStartedCount++,
                $event instanceof ToolCallCompleted => $this->toolCallCompletedCount++,
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

final class BlockingBeforeToolUseInterceptor implements CanInterceptAgentLifecycle
{
    public function intercept(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeToolUse => $context->withToolExecutionBlocked('blocked by new interceptor'),
            default => $context,
        };
    }
}

final class MixedStopPriorityInterceptor implements CanInterceptAgentLifecycle
{
    public function intercept(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeStep => $context->withState(
                $context->state()->withCurrentStep(new AgentStep()),
            ),
            HookTrigger::AfterStep => $context->withState(
                $context->state()->withStopSignal(new StopSignal(
                    reason: StopReason::Completed,
                    message: 'step finished',
                    source: 'AfterStepInterceptor',
                )),
            ),
            HookTrigger::OnStop => $context->withState(
                $context->state()->withStopSignal(new StopSignal(
                    reason: StopReason::UserRequested,
                    message: 'cancelled by user',
                    source: 'OnStopInterceptor',
                )),
            ),
            default => $context,
        };
    }
}

final class CrossLayerStopPriorityInterceptor implements CanInterceptAgentLifecycle
{
    private int $beforeStepCount = 0;

    public function intercept(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeStep => $this->onBeforeStep($context),
            HookTrigger::AfterStep => $this->onAfterStep($context),
            default => $context,
        };
    }

    private function onBeforeStep(HookContext $context): HookContext
    {
        $this->beforeStepCount++;

        return match ($this->beforeStepCount) {
            1 => $context->withState($context->state()->withCurrentStep(new AgentStep())),
            2 => $context->withState($context->state()->withStopSignal(new StopSignal(
                reason: StopReason::UserRequested,
                message: 'cancelled before step two',
                source: 'CheckpointInterceptor',
            ))),
            default => $context,
        };
    }

    private function onAfterStep(HookContext $context): HookContext
    {
        if ($this->beforeStepCount !== 1) {
            return $context;
        }

        return $context->withState(
            $context->state()
                ->withStopSignal(new StopSignal(
                    reason: StopReason::Completed,
                    message: 'step one finished',
                    source: 'ArchivedStepInterceptor',
                ))
                ->withExecutionContinued(),
        );
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
            public function useTools(AgentState $state): AgentState
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
            public function useTools(AgentState $state): AgentState
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
            public function useTools(AgentState $state): AgentState
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
            public function useTools(AgentState $state): AgentState
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

    it('uses new events in executor when withEventHandler() receives only events', function () {
        $tool = FakeTool::returning('test_tool', 'A test tool', 'ok');
        $tools = new Tools($tool);
        $oldListener = new CountingEventListener();
        $newListener = new CountingEventListener();
        $oldEvents = makeTestEvents($oldListener);
        $newEvents = makeTestEvents($newListener);
        $interceptor = new PassThroughInterceptor();

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state): AgentState
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

        $newLoop = $originalLoop->withEventHandler($newEvents);
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode([]),
        ]);
        $newLoop->toolExecutor()->executeTools(new ToolCalls($toolCall), AgentState::empty());

        expect($newListener->toolCallStartedCount)->toBe(1)
            ->and($newListener->toolCallCompletedCount)->toBe(1)
            ->and($oldListener->toolCallStartedCount)->toBe(0)
            ->and($oldListener->toolCallCompletedCount)->toBe(0);
    });

    it('uses new interceptor in executor when withInterceptor() receives only interceptor', function () {
        $tool = FakeTool::returning('test_tool', 'A test tool', 'ok');
        $tools = new Tools($tool);
        $events = new EventDispatcher();
        $oldInterceptor = new PassThroughInterceptor();
        $newInterceptor = new BlockingBeforeToolUseInterceptor();

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state): AgentState
            {
                return $state;
            }
        };

        $originalLoop = new AgentLoop(
            tools: $tools,
            toolExecutor: new ToolExecutor($tools, $events, $oldInterceptor),
            driver: $driver,
            events: $events,
            interceptor: $oldInterceptor,
        );

        $newLoop = $originalLoop->withInterceptor($newInterceptor);
        $toolCall = ToolCall::fromArray([
            'id' => 'call_1',
            'name' => 'test_tool',
            'arguments' => json_encode([]),
        ]);

        $originalResult = $originalLoop->toolExecutor()->executeTools(new ToolCalls($toolCall), AgentState::empty());
        $newResult = $newLoop->toolExecutor()->executeTools(new ToolCalls($toolCall), AgentState::empty());

        expect($originalResult->first()?->hasError())->toBeFalse()
            ->and($newResult->first()?->hasError())->toBeTrue();
    });

    it('emits executionFinished with Completed status, not InProgress', function () {
        $tools = new Tools();
        $listener = new CountingEventListener();
        $events = makeTestEvents($listener);
        $interceptor = new InjectStepInterceptor(new AgentStep());

        // Driver that returns immediately (no tool calls) — simulates LLM finish=stop
        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state): AgentState
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

    it('uses the highest-priority stop signal for stopped events and terminal status', function () {
        $tools = new Tools();
        $events = new EventDispatcher();
        $captured = [];
        $interceptor = new MixedStopPriorityInterceptor();

        $events->wiretap(static function (object $event) use (&$captured): void {
            $captured[] = $event;
        });

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state): AgentState
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
        $stopped = current(array_values(array_filter(
            $captured,
            static fn(object $event): bool => $event instanceof AgentExecutionStopped,
        )));

        expect($stopped)->toBeInstanceOf(AgentExecutionStopped::class)
            ->and($stopped->stopReason)->toBe(StopReason::UserRequested)
            ->and($stopped->stopMessage)->toBe('cancelled by user')
            ->and($stopped->source)->toBe('OnStopInterceptor')
            ->and($finalState->status())->toBe(ExecutionStatus::Stopped)
            ->and($finalState->stopReason())->toBe(StopReason::UserRequested);
    });

    it('prefers a higher-priority checkpoint signal over an archived step signal', function () {
        $tools = new Tools();
        $events = new EventDispatcher();
        $captured = [];
        $interceptor = new CrossLayerStopPriorityInterceptor();

        $events->wiretap(static function (object $event) use (&$captured): void {
            $captured[] = $event;
        });

        $driver = new class implements CanUseTools {
            public function useTools(AgentState $state): AgentState
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
        $stopped = current(array_values(array_filter(
            $captured,
            static fn(object $event): bool => $event instanceof AgentExecutionStopped,
        )));

        expect($stopped)->toBeInstanceOf(AgentExecutionStopped::class)
            ->and($stopped->stopReason)->toBe(StopReason::UserRequested)
            ->and($stopped->stopMessage)->toBe('cancelled before step two')
            ->and($stopped->source)->toBe('CheckpointInterceptor')
            ->and($finalState->status())->toBe(ExecutionStatus::Stopped)
            ->and($finalState->stopReason())->toBe(StopReason::UserRequested);
    });
});
