# Unified Lifecycle Pipeline: Full Extraction Investigation

## The Problem

Currently Agent has two problems:
1. **Mixed concerns**: Lifecycle methods mix core requirements with optional observability
2. **Two hook mechanisms**: Tool hooks (via ToolExecutor) vs lifecycle hooks (scaffolded but not integrated)

## Analysis: What's Actually Required?

After analyzing each lifecycle method, here's what Agent MUST do vs what's optional:

### Core Requirements (Agent cannot function without these)
- Initialize continuation criteria with execution start time
- Record each step in state
- Evaluate continuation after each step
- Create and record StepExecution
- Determine final status
- Handle errors and create failure steps

### Optional Behavior (Observability/Extensibility)
- All event emission
- All hook processing

**Key insight**: The core work is pure **state transformation**. Everything else wraps it.

## Radical Proposal: Pipeline-Based Agent

### Concept

What if Agent's `iterate()` becomes a pure loop that transforms state through a pipeline?

```php
class Agent implements CanControlAgentLoop
{
    public function __construct(
        private CanUseTools $driver,
        private Tools $tools,
        private CanExecuteToolCalls $toolExecutor,
        private ExecutionPipeline $pipeline,
    ) {}

    public function iterate(AgentState $state): iterable
    {
        $ctx = new ExecutionContext($this->driver, $this->tools, $this->toolExecutor);
        $state = $this->pipeline->beforeExecution($state, $ctx);

        while ($this->pipeline->shouldContinue($state, $ctx)) {
            $state = $this->pipeline->beforeStep($state, $ctx);
            $state = $this->pipeline->executeStep($state, $ctx);
            $state = $this->pipeline->afterStep($state, $ctx);
            yield $state;
        }

        yield $this->pipeline->afterExecution($state, $ctx);
    }
}
```

The Agent becomes a simple orchestrator. ALL behavior (core and optional) lives in the pipeline.

### ExecutionPipeline Interface

```php
interface ExecutionPipeline
{
    public function beforeExecution(AgentState $state, ExecutionContext $ctx): AgentState;
    public function shouldContinue(AgentState $state, ExecutionContext $ctx): bool;
    public function beforeStep(AgentState $state, ExecutionContext $ctx): AgentState;
    public function executeStep(AgentState $state, ExecutionContext $ctx): AgentState;
    public function afterStep(AgentState $state, ExecutionContext $ctx): AgentState;
    public function afterExecution(AgentState $state, ExecutionContext $ctx): AgentState;
    public function onError(Throwable $e, AgentState $state, ExecutionContext $ctx): AgentState;
}
```

### Composable Pipeline Implementation

```php
class ComposablePipeline implements ExecutionPipeline
{
    /** @var array<string, list<PhaseHandler>> */
    private array $handlers = [];

    public function addHandler(string $phase, PhaseHandler $handler): self
    {
        $this->handlers[$phase][] = $handler;
        return $this;
    }

    public function beforeExecution(AgentState $state, ExecutionContext $ctx): AgentState
    {
        return $this->runHandlers('before_execution', $state, $ctx);
    }

    public function executeStep(AgentState $state, ExecutionContext $ctx): AgentState
    {
        return $this->runHandlers('execute_step', $state, $ctx);
    }

    // ... other methods similar

    private function runHandlers(string $phase, AgentState $state, ExecutionContext $ctx): AgentState
    {
        foreach ($this->handlers[$phase] ?? [] as $handler) {
            $state = $handler->handle($state, $ctx);
        }
        return $state;
    }
}
```

### PhaseHandler Interface

```php
interface PhaseHandler
{
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState;
}
```

### Core Handlers (Required)

```php
// Initializes continuation tracking
class InitializeContinuationHandler implements PhaseHandler
{
    public function __construct(private ContinuationCriteria $criteria) {}

    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $this->criteria->executionStarted(new DateTimeImmutable());
        return $state;
    }
}

// Calls the driver to use tools
class UseToolsHandler implements PhaseHandler
{
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $rawStep = $ctx->driver->useTools($state, $ctx->tools, $ctx->toolExecutor);
        return $state->recordStep($rawStep);
    }
}

// Evaluates continuation and records StepExecution
class EvaluateContinuationHandler implements PhaseHandler
{
    public function __construct(private ContinuationCriteria $criteria) {}

    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $outcome = $this->criteria->evaluateAll($state);
        $stepExecution = new StepExecution(
            step: $state->currentStep(),
            outcome: $outcome,
            startedAt: $ctx->stepStartedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $state->stepCount(),
            id: $state->currentStep()->id(),
        );
        return $state->recordStepExecution($stepExecution);
    }
}

// Determines final status
class FinalizeExecutionHandler implements PhaseHandler
{
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $status = match ($state->stopReason()) {
            StopReason::ErrorForbade => AgentStatus::Failed,
            default => AgentStatus::Completed,
        };
        return $state->withStatus($status);
    }
}
```

### Optional Handlers (Observability)

```php
// Emits events
class EventEmitterHandler implements PhaseHandler
{
    public function __construct(
        private AgentEventEmitter $emitter,
        private string $eventMethod,
    ) {}

    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $this->emitter->{$this->eventMethod}($state);
        return $state;
    }
}

// Processes hooks
class HookHandler implements PhaseHandler
{
    public function __construct(
        private HookStack $hooks,
        private string $hookClass,
    ) {}

    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        return $this->hooks->process($this->hookClass, $state);
    }
}
```

### Building Pipelines

```php
class PipelineBuilder
{
    private ComposablePipeline $pipeline;

    public static function minimal(ContinuationCriteria $criteria): self
    {
        $builder = new self();
        $builder->pipeline = new ComposablePipeline();

        // Only add core handlers
        $builder->pipeline
            ->addHandler('before_execution', new InitializeContinuationHandler($criteria))
            ->addHandler('execute_step', new UseToolsHandler())
            ->addHandler('after_step', new EvaluateContinuationHandler($criteria))
            ->addHandler('after_execution', new FinalizeExecutionHandler());

        return $builder;
    }

    public function withEvents(AgentEventEmitter $emitter): self
    {
        $this->pipeline
            ->addHandler('before_execution', new EventEmitterHandler($emitter, 'executionStarted'))
            ->addHandler('before_step', new EventEmitterHandler($emitter, 'stepStarted'))
            ->addHandler('after_step', new EventEmitterHandler($emitter, 'stepCompleted'))
            ->addHandler('after_execution', new EventEmitterHandler($emitter, 'executionFinished'));

        return $this;
    }

    public function withHooks(HookStack $hooks): self
    {
        $this->pipeline
            ->addHandler('before_execution', new HookHandler($hooks, ExecutionStartHook::class))
            ->addHandler('before_step', new HookHandler($hooks, StepStartHook::class))
            ->addHandler('after_step', new HookHandler($hooks, StepEndHook::class))
            ->addHandler('after_execution', new HookHandler($hooks, StopHook::class));

        return $this;
    }

    public function build(): ExecutionPipeline
    {
        return $this->pipeline;
    }
}
```

### Usage

```php
// Minimal agent (no events, no hooks)
$pipeline = PipelineBuilder::minimal($criteria)->build();
$agent = new Agent($driver, $tools, $toolExecutor, $pipeline);

// With events
$pipeline = PipelineBuilder::minimal($criteria)
    ->withEvents($eventEmitter)
    ->build();

// With everything
$pipeline = PipelineBuilder::minimal($criteria)
    ->withEvents($eventEmitter)
    ->withHooks($hookStack)
    ->build();
```

---

## Unified Hook Handling

With this approach, tool hooks become just another handler:

```php
// Tool hooks as pipeline handlers
class BeforeToolHookHandler implements PhaseHandler
{
    public function __construct(private HookStack $hooks) {}

    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        // Process BeforeToolHook for each pending tool call
        foreach ($state->pendingToolCalls() as $toolCall) {
            $this->hooks->process(BeforeToolHook::class, $toolCall);
        }
        return $state;
    }
}
```

This eliminates the need for ToolExecutor to handle hooks separately - they're all in the pipeline!

### Phases for Tools

Add new phases specifically for tool execution:

```php
interface ExecutionPipeline
{
    // ... existing methods ...

    // Tool-specific phases (called within executeStep)
    public function beforeToolCall(ToolCall $call, AgentState $state, ExecutionContext $ctx): ToolCall;
    public function afterToolCall(ToolCall $call, ToolResult $result, AgentState $state, ExecutionContext $ctx): ToolResult;
}
```

Or model tool execution as sub-phases within `execute_step`:

```
execute_step:
  └── for each tool call:
       ├── before_tool_call
       ├── execute_tool
       └── after_tool_call
```

---

## Benefits of This Approach

### 1. **Single Unified Mechanism**
All hooks (lifecycle and tool) work through the same pipeline. No mental overhead of "which pattern handles which hook."

### 2. **Agent Becomes Minimal**
```php
// The entire Agent::iterate() method
public function iterate(AgentState $state): iterable
{
    $ctx = new ExecutionContext($this->driver, $this->tools, $this->toolExecutor);
    $state = $this->pipeline->beforeExecution($state, $ctx);

    while ($this->pipeline->shouldContinue($state, $ctx)) {
        $state = $this->pipeline->beforeStep($state, $ctx);
        $state = $this->pipeline->executeStep($state, $ctx);
        $state = $this->pipeline->afterStep($state, $ctx);
        yield $state;
    }

    yield $this->pipeline->afterExecution($state, $ctx);
}
```

Just ~15 lines. All behavior is externalized.

### 3. **Fully Composable**
Add any behavior by adding handlers. Remove behavior by not adding handlers. Order is explicit.

### 4. **Easy Testing**
Each handler is independently testable. Pipelines can be tested in isolation.

### 5. **No Inheritance**
Pure composition. Want different behavior? Build a different pipeline.

---

## Challenges

### 1. **Error Handling**
Need to handle errors at each phase. Options:
- Wrap each handler call in try/catch
- Add error handlers to pipeline
- Use Result types instead of exceptions

### 2. **Context Passing**
ExecutionContext needs to carry timing, current step info, etc. May grow complex.

### 3. **Handler Ordering**
Order matters (core must come before hooks in some cases). Need clear conventions.

### 4. **Migration**
Significant refactoring. Need careful migration path.

---

## Migration Path

### Phase 1: Create Pipeline Infrastructure
1. Create `ExecutionPipeline` interface
2. Create `PhaseHandler` interface
3. Create `ComposablePipeline` implementation
4. Create core handlers

### Phase 2: Dual Mode
1. Add optional pipeline to Agent constructor
2. If pipeline provided, use it
3. Otherwise, use existing lifecycle methods
4. Existing code keeps working

### Phase 3: Move Behavior to Handlers
1. Extract event emission to EventEmitterHandler
2. Extract hook processing to HookHandler
3. Migrate existing capabilities to use pipeline

### Phase 4: Clean Up
1. Remove lifecycle methods from Agent
2. Make pipeline required
3. Update AgentBuilder to always create pipeline

---

## Alternative: Lighter Touch

If full pipeline feels too radical, a lighter approach:

```php
interface LifecycleInterceptor
{
    public function intercept(string $phase, AgentState $state, callable $next): AgentState;
}

class Agent
{
    public function __construct(
        // ... existing deps
        private ?LifecycleInterceptor $interceptor = null,
    ) {}

    protected function onBeforeExecution(AgentState $state): AgentState
    {
        // Core work always happens
        $this->continuationCriteria->executionStarted(new DateTimeImmutable());

        // Optional interception
        return $this->intercept('before_execution', $state, fn($s) => $s);
    }

    private function intercept(string $phase, AgentState $state, callable $core): AgentState
    {
        if ($this->interceptor === null) {
            return $core($state);
        }
        return $this->interceptor->intercept($phase, $state, $core);
    }
}
```

This keeps core behavior in Agent but allows interception for hooks/events.

---

## Recommendation

**Go with the full pipeline approach** because:

1. It solves both problems completely (unified hooks, full extraction)
2. The Agent becomes trivially simple
3. All behavior is explicitly composed
4. Testing becomes much easier
5. The migration path allows gradual adoption

The complexity is front-loaded in the design, but ongoing maintenance and evolution become much simpler.
