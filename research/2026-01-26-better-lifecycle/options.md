# Hook Extraction Options: Making Hooks an Opt-In Capability

## Goal

Extract hooks from the core agent execution model to make them an opt-in capability. The base `Agent` class should be minimal and easily evolvable, with hooks added through composition rather than inheritance.

## Current State Analysis

### Lifecycle Methods in Agent

The `Agent` class has these protected lifecycle methods:
- `onBeforeExecution(AgentState $state)`
- `onBeforeStep(AgentState $state)`
- `onBeforeToolUse(AgentState $state)` - currently empty
- `onAfterToolUse(AgentState $state, AgentStep $rawStep)`
- `onAfterStep(AgentState $state)`
- `onAfterExecution(AgentState $state)`
- `onError(Throwable $error, AgentState $state)`

### Hook Types Designed

The hooks system was designed for:
- `ExecutionStartHook` → maps to `onBeforeExecution`
- `StepStartHook` → maps to `onBeforeStep`
- `BeforeToolHook` → maps to `onBeforeToolUse`
- `AfterToolHook` → maps to `onAfterToolUse`
- `StepEndHook` → maps to `onAfterStep`
- `StopHook` → maps to `onAfterExecution`
- `ErrorHook` → maps to `onError`

### Current Integration Status

- **Tool hooks** (`BeforeToolHook`, `AfterToolHook`): Integrated via `ToolExecutor` - these work
- **Lifecycle hooks** (all others): NOT integrated into base `Agent` - currently dead code

---

## Option 1: Strategy/Delegate Pattern

### Concept

Create a `LifecycleHandler` interface that the agent delegates to for all lifecycle events. The base agent uses a no-op implementation; hooks are added via a `HookAwareLifecycleHandler`.

### Interface

```php
interface LifecycleHandler
{
    public function onExecutionStart(AgentState $state): AgentState;
    public function onStepStart(AgentState $state): AgentState;
    public function onStepEnd(AgentState $state): AgentState;
    public function onExecutionEnd(AgentState $state): AgentState;
    public function onError(Throwable $error, AgentState $state): AgentState;
}
```

### Implementations

```php
// Default: does nothing
class PassthroughLifecycleHandler implements LifecycleHandler
{
    public function onExecutionStart(AgentState $state): AgentState { return $state; }
    // ... other methods return state unchanged
}

// With hooks
class HookAwareLifecycleHandler implements LifecycleHandler
{
    public function __construct(private HookStack $hooks) {}

    public function onExecutionStart(AgentState $state): AgentState {
        return $this->hooks->process(ExecutionStartHook::class, $state);
    }
    // ...
}
```

### Agent Changes

```php
class Agent
{
    public function __construct(
        // ... existing deps
        private readonly LifecycleHandler $lifecycle = new PassthroughLifecycleHandler(),
    ) {}

    protected function onBeforeExecution(AgentState $state): AgentState {
        $this->eventEmitter->executionStarted($state, count($this->tools->names()));
        return $this->lifecycle->onExecutionStart($state);
    }
}
```

### Pros
- Clean separation of concerns
- Easy to test lifecycle handlers in isolation
- Agent remains focused on orchestration
- Multiple lifecycle handler implementations possible

### Cons
- Adds one interface + implementations
- Slightly more indirection

---

## Option 2: Decorator Pattern for ToolExecutor

### Concept

Since tool hooks already work via `ToolExecutor`, extend this pattern. Create a `HookAwareToolExecutor` decorator and similar decorators for lifecycle events by wrapping the agent itself.

### Implementation

```php
class HookAwareToolExecutor implements CanExecuteToolCalls
{
    public function __construct(
        private CanExecuteToolCalls $inner,
        private HookStack $hooks,
    ) {}

    public function execute(ToolCall $toolCall, Tools $tools): ToolResult {
        $this->hooks->process(BeforeToolHook::class, $toolCall);
        $result = $this->inner->execute($toolCall, $tools);
        $this->hooks->process(AfterToolHook::class, $toolCall, $result);
        return $result;
    }
}
```

For lifecycle hooks, wrap at the agent level:

```php
class HookAwareAgent implements CanControlAgentLoop
{
    public function __construct(
        private CanControlAgentLoop $inner,
        private HookStack $hooks,
    ) {}

    public function execute(AgentState $state): AgentState {
        $state = $this->hooks->process(ExecutionStartHook::class, $state);
        $result = $this->inner->execute($state);
        return $this->hooks->process(StopHook::class, $result);
    }
}
```

### Pros
- Pure composition, no agent changes needed
- Decorators can be stacked
- Very flexible

### Cons
- `iterate()` method is harder to wrap (generator)
- Need to handle step-level hooks differently
- May require exposing more internal events

---

## Option 3: Middleware Pipeline

### Concept

Replace lifecycle methods with a middleware pipeline. Each step of execution passes through a chain of middleware that can modify state or halt execution.

### Interface

```php
interface AgentMiddleware
{
    public function process(AgentState $state, callable $next): AgentState;
}
```

### Implementations

```php
class HookMiddleware implements AgentMiddleware
{
    public function __construct(
        private HookStack $hooks,
        private string $hookClass,
    ) {}

    public function process(AgentState $state, callable $next): AgentState {
        $state = $this->hooks->process($this->hookClass, $state);
        return $next($state);
    }
}

class EventEmitterMiddleware implements AgentMiddleware
{
    public function process(AgentState $state, callable $next): AgentState {
        $this->emitter->stepStarted($state);
        $result = $next($state);
        $this->emitter->stepCompleted($result);
        return $result;
    }
}
```

### Pipeline

```php
class MiddlewarePipeline
{
    public function __construct(private array $middleware = []) {}

    public function process(AgentState $state, callable $core): AgentState {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn($next, $mw) => fn($s) => $mw->process($s, $next),
            $core
        );
        return $pipeline($state);
    }
}
```

### Pros
- Very flexible and extensible
- Standard pattern (PSR-15 style)
- Easy to add/remove/reorder behavior
- Naturally supports cross-cutting concerns

### Cons
- Significant refactoring of Agent internals
- Different mental model from current code
- Need separate pipelines for different lifecycle phases

---

## Option 4: Hybrid Approach (Recommended)

### Concept

Combine Options 1 and 2:
1. Use **Strategy pattern** for lifecycle hooks (cleaner, more explicit)
2. Keep **Decorator pattern** for tool hooks (already working)
3. Add **AgentCapability** for easy opt-in

### Implementation

#### Step 1: Create LifecycleHandler interface

```php
interface CanHandleLifecycle
{
    public function beforeExecution(AgentState $state): AgentState;
    public function beforeStep(AgentState $state): AgentState;
    public function afterStep(AgentState $state): AgentState;
    public function afterExecution(AgentState $state): AgentState;
    public function onError(Throwable $error, AgentState $state): ErrorHandlingResult;
}
```

#### Step 2: Default implementation (no-op)

```php
final class NullLifecycleHandler implements CanHandleLifecycle
{
    public function beforeExecution(AgentState $state): AgentState { return $state; }
    public function beforeStep(AgentState $state): AgentState { return $state; }
    public function afterStep(AgentState $state): AgentState { return $state; }
    public function afterExecution(AgentState $state): AgentState { return $state; }
    public function onError(Throwable $error, AgentState $state): ErrorHandlingResult {
        throw $error; // Re-throw by default
    }
}
```

#### Step 3: Hook-aware implementation

```php
final class HookLifecycleHandler implements CanHandleLifecycle
{
    public function __construct(private HookStack $hooks) {}

    public function beforeExecution(AgentState $state): AgentState {
        return $this->hooks->process(ExecutionStartHook::class, $state);
    }
    // ... other methods
}
```

#### Step 4: Update Agent constructor

```php
class Agent
{
    public function __construct(
        private readonly Tools $tools,
        private readonly CanExecuteToolCalls $toolExecutor,
        private readonly CanHandleAgentErrors $errorHandler,
        private readonly ?CanApplyProcessors $processors,
        private readonly ContinuationCriteria $continuationCriteria,
        private readonly CanUseTools $driver,
        private readonly CanHandleLifecycle $lifecycle, // NEW
        AgentEventEmitter $eventEmitter,
        ?CanHandleEvents $events = null,
    ) {}
}
```

#### Step 5: Create capability for opt-in

```php
class UseLifecycleHooks implements AgentCapability
{
    public function __construct(
        private HookStack $hooks,
    ) {}

    public function install(AgentBuilder $builder): void {
        $builder->withLifecycleHandler(
            new HookLifecycleHandler($this->hooks)
        );
    }
}
```

#### Step 6: Usage

```php
// Without hooks (default, minimal)
$agent = AgentBuilder::new()
    ->withTools($tools)
    ->build();

// With lifecycle hooks (opt-in)
$agent = AgentBuilder::new()
    ->withTools($tools)
    ->withCapability(new UseLifecycleHooks($hookStack))
    ->build();
```

### Pros
- Minimal changes to Agent core
- Clear separation: base Agent is simple, hooks are added via capability
- Consistent with existing AgentCapability pattern
- Tool hooks continue working as-is
- Easy to understand: lifecycle handler is just another dependency

### Cons
- One new interface + implementations
- Need to update AgentBuilder

---

## Migration Path

### Phase 1: Add LifecycleHandler (non-breaking)

1. Create `CanHandleLifecycle` interface
2. Create `NullLifecycleHandler` (default)
3. Add optional `$lifecycle` parameter to Agent constructor with default
4. Update Agent lifecycle methods to delegate to handler
5. All existing code continues working unchanged

### Phase 2: Create Hook Implementation

1. Move hook processing from scattered locations to `HookLifecycleHandler`
2. Create `UseLifecycleHooks` capability
3. Update tests

### Phase 3: Clean Up (optional)

1. Remove lifecycle hooks from places where they were scaffolded but never integrated
2. Simplify Agent's protected methods to just delegate + emit events

---

## Recommendation

**Go with Option 4 (Hybrid)** because:

1. **Minimal risk**: Agent changes are additive, not destructive
2. **Backward compatible**: Default behavior unchanged
3. **Consistent**: Uses existing patterns (AgentCapability, dependency injection)
4. **Testable**: Each piece can be tested in isolation
5. **Evolvable**: Easy to add more lifecycle handlers later (logging, metrics, tracing)

The key insight is that hooks are just one possible implementation of lifecycle handling. By abstracting to `CanHandleLifecycle`, we open the door for other implementations without coupling the agent to any specific one.
