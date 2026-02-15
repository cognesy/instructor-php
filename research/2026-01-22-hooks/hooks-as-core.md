# Hooks as Core: Redesigning Agent Architecture

This document explores a fundamental redesign of the Agent/AgentBuilder mechanism where hooks are not a capability bolted on, but a core primitive that defines the entire execution model.

## The Question

> Instead of making hooks a capability (UseHooks), should we redesign the entire Agent/AgentBuilder mechanism to make hooks a natural part of the design?

**Short answer:** Yes, but with careful consideration. Hooks-as-core leads to a more unified, extensible architecture—but requires rethinking several current abstractions.

---

## 1. Current Architecture Analysis

### What We Have Now

```
AgentBuilder
├── Tools (collection)
├── Processors (middleware chain)
│   ├── PreProcessors
│   ├── BaseProcessors (token, messages, metadata)
│   └── PostProcessors
├── ContinuationCriteria (step termination logic)
├── Driver (LLM tool calling)
├── ToolExecutor (tool invocation)
└── Events (observability)
```

**Execution flow:**
```
finalStep() → while(hasNextStep) → nextStep() → processors.apply(performStep)
                                                        ↓
                                              makeNextStep → driver.useTools
                                                        ↓
                                              toolExecutor.useTool (foreach tool call)
                                                        ↓
                                              applyStep → recordStep
                                                        ↓
                                              continuationCriteria.evaluateAll
```

### Pain Points

1. **Multiple extension mechanisms** - Processors, criteria, events, onBuild callbacks all do different things
2. **No unified lifecycle model** - Extension points are discovered, not designed
3. **Hooks as afterthought** - Would require wrapping existing components
4. **Events are observe-only** - Can't intercept/modify, only react
5. **Processor vs Hook confusion** - Both transform state, but different patterns

---

## 2. Hooks-as-Core Vision

### Core Insight

> **Everything that happens during agent execution is a hook point.**

Instead of:
- Processors that transform state
- Criteria that evaluate continuation
- Events that notify observers
- Hooks that intercept operations

We have:
- **Lifecycle hooks** that define what happens at each point
- **All behavior is hookable, composable, and replaceable**

### Unified Model

```
AgentRuntime
├── HookRegistry (all lifecycle hooks)
│   ├── ExecutionStart hooks
│   ├── StepStart hooks
│   ├── PreInference hooks
│   ├── PostInference hooks
│   ├── PreToolUse hooks
│   ├── PostToolUse hooks
│   ├── StepEnd hooks
│   ├── ShouldContinue hooks
│   ├── ExecutionEnd hooks
│   └── OnError hooks
├── Driver (LLM interface)
├── Tools (capability collection)
└── State (immutable execution state)
```

**Key shift:** Processors, criteria, and events become **hook implementations**.

---

## 3. Proposed Architecture

### 3.1 Hook Lifecycle Events

```php
enum HookPoint: string
{
    // Execution lifecycle
    case ExecutionStart = 'execution.start';
    case ExecutionEnd = 'execution.end';

    // Step lifecycle
    case StepStart = 'step.start';
    case StepEnd = 'step.end';

    // Inference lifecycle
    case PreInference = 'inference.pre';
    case PostInference = 'inference.post';

    // Tool lifecycle
    case PreToolUse = 'tool.pre';
    case PostToolUse = 'tool.post';

    // Control flow
    case ShouldContinue = 'control.should_continue';
    case OnError = 'control.on_error';

    // Input handling
    case UserInput = 'input.user';

    // Subagent lifecycle
    case SubagentStart = 'subagent.start';
    case SubagentEnd = 'subagent.end';
}
```

### 3.2 Hook Contract

```php
/**
 * A hook is an action that executes at a lifecycle point.
 * Hooks can observe, modify, or control execution flow.
 */
interface Hook
{
    /**
     * Execute the hook with the given context.
     *
     * @return HookResult The result determines what happens next
     */
    public function __invoke(HookContext $context): HookResult;
}

/**
 * HookResult communicates back to the runtime.
 */
final readonly class HookResult
{
    public function __construct(
        public HookSignal $signal = HookSignal::Continue,
        public ?AgentState $modifiedState = null,
        public ?array $modifiedToolArgs = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}

    // Factories for common results
    public static function continue(): self;
    public static function stop(string $reason): self;
    public static function deny(string $reason): self;
    public static function allow(): self;
    public static function withState(AgentState $state): self;
    public static function withToolArgs(array $args): self;
}

enum HookSignal: string
{
    case Continue = 'continue';     // Proceed normally
    case Stop = 'stop';             // Stop execution
    case Deny = 'deny';             // Block current operation
    case Allow = 'allow';           // Explicitly allow (bypass other checks)
    case Skip = 'skip';             // Skip remaining hooks for this point
    case Retry = 'retry';           // Retry current operation
}
```

### 3.3 Hook Context

```php
/**
 * Context provides everything a hook needs to make decisions.
 */
final readonly class HookContext
{
    public function __construct(
        public HookPoint $point,
        public AgentState $state,
        public AgentRuntime $runtime,

        // Point-specific data (nullable based on context)
        public ?ToolCall $toolCall = null,
        public ?ToolExecution $toolExecution = null,
        public ?InferenceResponse $inferenceResponse = null,
        public ?string $userInput = null,
        public ?Throwable $error = null,

        // Metadata for hook communication
        public array $metadata = [],
    ) {}

    // Convenience accessors
    public function toolName(): ?string { ... }
    public function stepIndex(): int { ... }
    public function isFirstStep(): bool { ... }
    public function previousHookResults(): array { ... }
}
```

### 3.4 Hook Registry

```php
/**
 * Registry manages all hooks with priority ordering.
 */
final class HookRegistry
{
    /** @var array<string, SplPriorityQueue<Hook>> */
    private array $hooks = [];

    public function register(
        HookPoint $point,
        Hook $hook,
        int $priority = 0,
        ?HookMatcher $matcher = null,
    ): self {
        // ...
    }

    public function forPoint(HookPoint $point): HookCollection
    {
        // Returns hooks sorted by priority (higher first)
    }
}
```

### 3.5 AgentRuntime (Replaces Agent)

```php
/**
 * AgentRuntime executes the agent lifecycle through hooks.
 *
 * The runtime itself has minimal logic—hooks define behavior.
 */
final class AgentRuntime
{
    public function __construct(
        private readonly HookRegistry $hooks,
        private readonly Tools $tools,
        private readonly CanUseTools $driver,
    ) {}

    public function execute(AgentState $state): AgentState
    {
        // ExecutionStart
        $state = $this->runHooks(HookPoint::ExecutionStart, $state);

        while ($this->shouldContinue($state)) {
            $state = $this->executeStep($state);
        }

        // ExecutionEnd
        return $this->runHooks(HookPoint::ExecutionEnd, $state);
    }

    private function executeStep(AgentState $state): AgentState
    {
        // StepStart
        $state = $this->runHooks(HookPoint::StepStart, $state);

        // PreInference
        $state = $this->runHooks(HookPoint::PreInference, $state);

        // Driver inference
        $step = $this->driver->useTools($state, $this->tools, $this);

        // PostInference
        $context = HookContext::forPostInference($state, $step);
        $state = $this->runHooks(HookPoint::PostInference, $state, $context);

        // Tool execution (with PreToolUse/PostToolUse hooks)
        $state = $this->executeToolCalls($state, $step->toolCalls());

        // Record step
        $state = $state->recordStep($step);

        // StepEnd
        return $this->runHooks(HookPoint::StepEnd, $state);
    }

    private function executeToolCalls(AgentState $state, ToolCalls $calls): AgentState
    {
        foreach ($calls as $toolCall) {
            // PreToolUse - can deny or modify
            $context = HookContext::forPreToolUse($state, $toolCall);
            $result = $this->runHooksWithResult(HookPoint::PreToolUse, $context);

            if ($result->signal === HookSignal::Deny) {
                $state = $state->recordDeniedToolCall($toolCall, $result->reason);
                continue;
            }

            $effectiveCall = $result->modifiedToolArgs
                ? $toolCall->withArgs($result->modifiedToolArgs)
                : $toolCall;

            // Execute tool
            $execution = $this->executeTool($effectiveCall, $state);

            // PostToolUse
            $context = HookContext::forPostToolUse($state, $execution);
            $this->runHooks(HookPoint::PostToolUse, $state, $context);

            $state = $state->recordToolExecution($execution);
        }

        return $state;
    }

    private function shouldContinue(AgentState $state): bool
    {
        $context = HookContext::forShouldContinue($state);
        $result = $this->runHooksWithResult(HookPoint::ShouldContinue, $context);

        // Hook can force stop or force continue
        return match ($result->signal) {
            HookSignal::Stop => false,
            HookSignal::Continue => true,
            default => $this->defaultContinuationLogic($state),
        };
    }

    private function runHooks(HookPoint $point, AgentState $state, ?HookContext $context = null): AgentState
    {
        $context ??= new HookContext(point: $point, state: $state, runtime: $this);

        foreach ($this->hooks->forPoint($point) as $hook) {
            $result = $hook($context);

            // Apply state modifications
            if ($result->modifiedState !== null) {
                $state = $result->modifiedState;
                $context = $context->withState($state);
            }

            // Handle control signals
            if ($result->signal === HookSignal::Skip) {
                break;
            }
        }

        return $state;
    }
}
```

### 3.6 AgentBuilder (Simplified)

```php
/**
 * Builder creates AgentRuntime with configured hooks.
 */
final class AgentBuilder
{
    private HookRegistry $hooks;
    private Tools $tools;
    private ?CanUseTools $driver = null;

    public static function new(): self
    {
        $builder = new self();
        $builder->hooks = new HookRegistry();
        $builder->tools = new Tools();

        // Register default hooks (what was previously "base processors")
        $builder->registerDefaultHooks();

        return $builder;
    }

    /**
     * Register a hook at a lifecycle point.
     */
    public function hook(
        HookPoint $point,
        Hook|callable $hook,
        int $priority = 0,
        ?HookMatcher $matcher = null,
    ): self {
        $hookInstance = $hook instanceof Hook
            ? $hook
            : new CallableHook($hook);

        $this->hooks->register($point, $hookInstance, $priority, $matcher);
        return $this;
    }

    /**
     * Convenience: Hook before tool use.
     */
    public function beforeToolUse(
        Hook|callable $hook,
        ?string $toolPattern = null,
        int $priority = 0,
    ): self {
        $matcher = $toolPattern ? new ToolNameMatcher($toolPattern) : null;
        return $this->hook(HookPoint::PreToolUse, $hook, $priority, $matcher);
    }

    /**
     * Convenience: Hook after tool use.
     */
    public function afterToolUse(
        Hook|callable $hook,
        ?string $toolPattern = null,
        int $priority = 0,
    ): self {
        $matcher = $toolPattern ? new ToolNameMatcher($toolPattern) : null;
        return $this->hook(HookPoint::PostToolUse, $hook, $priority, $matcher);
    }

    /**
     * Convenience: Add continuation check.
     */
    public function continueWhen(Hook|callable $hook, int $priority = 0): self
    {
        return $this->hook(HookPoint::ShouldContinue, $hook, $priority);
    }

    /**
     * Convenience: Add step processor (runs at StepEnd).
     */
    public function process(Hook|callable $hook, int $priority = 0): self
    {
        return $this->hook(HookPoint::StepEnd, $hook, $priority);
    }

    /**
     * Apply a capability (capabilities register their hooks).
     */
    public function with(AgentCapability $capability): self
    {
        $capability->install($this);
        return $this;
    }

    public function build(): AgentRuntime
    {
        return new AgentRuntime(
            hooks: $this->hooks,
            tools: $this->tools,
            driver: $this->driver ?? $this->buildDefaultDriver(),
        );
    }

    private function registerDefaultHooks(): void
    {
        // Token accumulation (was AccumulateTokenUsage processor)
        $this->hook(HookPoint::StepEnd, new AccumulateTokensHook(), priority: 100);

        // Message appending (was AppendStepMessages processor)
        $this->hook(HookPoint::StepEnd, new AppendMessagesHook(), priority: 90);

        // Default continuation: stop when no tool calls
        $this->hook(HookPoint::ShouldContinue, new StopOnNoToolCallsHook(), priority: 0);
    }
}
```

---

## 4. Migration: Current Concepts → Hooks

### 4.1 Processors → Hooks

| Current Processor | Hook Point | Priority |
|-------------------|------------|----------|
| ApplyCachedContext | ExecutionStart | 100 |
| AccumulateTokenUsage | StepEnd | 100 |
| AppendContextMetadata | StepEnd | 90 |
| AppendStepMessages | StepEnd | 80 |
| TodoReminderProcessor | StepStart | 50 |
| TodoRenderProcessor | StepEnd | 50 |
| SelfCriticProcessor | StepEnd | 40 |

**Example migration:**

```php
// Before: Processor
final class AccumulateTokenUsage implements CanProcessAnyState
{
    public function canProcess(object $state): bool { ... }
    public function process(object $state, ?callable $next = null): object { ... }
}

// After: Hook
final class AccumulateTokensHook implements Hook
{
    public function __invoke(HookContext $context): HookResult
    {
        $step = $context->state->currentStep();
        if ($step === null) {
            return HookResult::continue();
        }

        $newState = $context->state->withAccumulatedUsage($step->usage());
        return HookResult::withState($newState);
    }
}
```

### 4.2 ContinuationCriteria → ShouldContinue Hooks

| Current Criterion | Becomes |
|-------------------|---------|
| StepsLimit | MaxStepsHook at ShouldContinue |
| TokenUsageLimit | MaxTokensHook at ShouldContinue |
| ExecutionTimeLimit | TimeoutHook at ShouldContinue |
| ToolCallPresenceCheck | StopOnNoToolCallsHook at ShouldContinue |
| SelfCriticContinuationCheck | SelfCriticHook at ShouldContinue |

**Example migration:**

```php
// Before: ContinuationCriterion
final class StepsLimit implements CanEvaluateContinuation
{
    public function evaluate(object $state): ContinuationEvaluation { ... }
}

// After: Hook
final class MaxStepsHook implements Hook
{
    public function __construct(private int $maxSteps) {}

    public function __invoke(HookContext $context): HookResult
    {
        if ($context->state->stepCount() >= $this->maxSteps) {
            return HookResult::stop("Maximum steps ({$this->maxSteps}) reached");
        }
        return HookResult::continue();
    }
}
```

### 4.3 Events → Observable Hooks

Events become hooks that observe without modifying:

```php
// Observable hook pattern
final class EventEmittingHook implements Hook
{
    public function __construct(
        private readonly CanHandleEvents $events,
        private readonly string $eventClass,
    ) {}

    public function __invoke(HookContext $context): HookResult
    {
        $event = $this->createEvent($context);
        $this->events->dispatch($event);
        return HookResult::continue();  // Never modifies, just observes
    }
}

// Usage in builder
$builder->hook(HookPoint::StepStart, new EventEmittingHook($events, AgentStepStarted::class));
$builder->hook(HookPoint::StepEnd, new EventEmittingHook($events, AgentStepCompleted::class));
```

### 4.4 Capabilities → Hook Registrations

```php
// Before
final class UseSelfCritique implements AgentCapability
{
    public function install(AgentBuilder $builder): void
    {
        $builder->addProcessor(new SelfCriticProcessor(...));
        $builder->addContinuationCriteria(new SelfCriticContinuationCheck(...));
    }
}

// After
final class UseSelfCritique implements AgentCapability
{
    public function install(AgentBuilder $builder): void
    {
        // Self-critic evaluation at step end
        $builder->hook(
            HookPoint::StepEnd,
            new SelfCriticEvaluationHook($this->maxIterations, $this->llmPreset),
            priority: 40,
        );

        // Continuation check
        $builder->hook(
            HookPoint::ShouldContinue,
            new SelfCriticContinueHook($this->maxIterations),
            priority: 50,
        );
    }
}
```

---

## 5. Benefits of Hooks-as-Core

### 5.1 Unified Extension Model

**Before:** 5 different extension mechanisms (processors, criteria, events, onBuild, capabilities)

**After:** 1 mechanism (hooks) with clear semantics

```php
// Everything is a hook
$builder
    ->hook(HookPoint::PreToolUse, $securityValidator)
    ->hook(HookPoint::PostToolUse, $formatter)
    ->hook(HookPoint::ShouldContinue, $budgetChecker)
    ->hook(HookPoint::StepEnd, $telemetry)
    ->hook(HookPoint::OnError, $errorReporter);
```

### 5.2 Deterministic Control

Hooks provide **guaranteed execution points** that prompting cannot:

```php
// This ALWAYS runs before any bash command
$builder->beforeToolUse(
    fn(HookContext $ctx) => $this->validateCommand($ctx),
    toolPattern: 'Bash',
);

// This ALWAYS runs after file writes
$builder->afterToolUse(
    fn(HookContext $ctx) => $this->formatCode($ctx),
    toolPattern: 'Write|Edit',
);
```

### 5.3 Composable and Orderable

Priority-based execution means hooks compose predictably:

```php
// Security check first (high priority)
$builder->beforeToolUse($securityHook, priority: 100);

// Then logging (medium priority)
$builder->beforeToolUse($loggingHook, priority: 50);

// Then modification (low priority)
$builder->beforeToolUse($argModifierHook, priority: 10);
```

### 5.4 Natural Testing

Hooks are simple functions, easily testable:

```php
public function testSecurityHookBlocksDangerousCommands(): void
{
    $hook = new BashSecurityHook(allowedPaths: ['/safe']);
    $context = HookContext::forPreToolUse(
        state: AgentState::empty(),
        toolCall: new ToolCall('Bash', ['command' => 'rm -rf /']),
    );

    $result = $hook($context);

    $this->assertEquals(HookSignal::Deny, $result->signal);
}
```

### 5.5 Capability Isolation

Capabilities register hooks at specific points with explicit priorities:

```php
final class UseTaskPlanning implements AgentCapability
{
    public function install(AgentBuilder $builder): void
    {
        // Tasks capability owns these hook points
        $builder->hook(HookPoint::StepStart, new TodoReminderHook(), priority: 50);
        $builder->hook(HookPoint::StepEnd, new TodoPersistHook(), priority: 50);
        $builder->hook(HookPoint::StepEnd, new TodoRenderHook(), priority: 45);
    }
}
```

---

## 6. Trade-offs and Concerns

### 6.1 Learning Curve

**Concern:** Developers familiar with middleware patterns may need to adjust.

**Mitigation:** Provide clear mapping documentation and convenience methods:
```php
// Familiar middleware-style API
$builder->process($hook);  // → hooks StepEnd
$builder->continueWhen($check);  // → hooks ShouldContinue
```

### 6.2 Performance

**Concern:** Hook dispatch overhead for every lifecycle point.

**Mitigation:**
- Short-circuit when no hooks registered
- Compile hook chains at build time
- Cache matcher evaluations

```php
// Build-time optimization
final class CompiledHookRegistry
{
    public static function compile(HookRegistry $registry): self
    {
        // Pre-compute sorted arrays, pre-evaluate static matchers
    }
}
```

### 6.3 Debugging Complexity

**Concern:** Harder to trace execution through many hooks.

**Mitigation:**
- Built-in hook execution tracing
- Hook metadata for identification
- Debug mode with detailed logging

```php
$builder->withDebug(true);  // Logs all hook executions

// Each hook can have identity
$builder->hook(
    HookPoint::PreToolUse,
    new SecurityHook(),
    name: 'security.bash_validator',  // For tracing
);
```

### 6.4 Hook Ordering Conflicts

**Concern:** Multiple capabilities registering at same priority.

**Mitigation:**
- Explicit priority bands
- Capability priority guidelines
- Conflict detection at build time

```php
// Priority bands
const PRIORITY_SECURITY = 100;    // Security hooks first
const PRIORITY_TRANSFORM = 50;    // Transformations middle
const PRIORITY_OBSERVE = 10;      // Observation last

// Build-time validation
$builder->validateNoPriorityConflicts();
```

---

## 7. Comparison: Capability vs Core

| Aspect | Hooks as Capability | Hooks as Core |
|--------|---------------------|---------------|
| **Adoption** | Optional, gradual | All-in, breaking change |
| **Complexity** | Wrappers needed | Native integration |
| **Performance** | Extra indirection | Direct dispatch |
| **Consistency** | Mixed extension models | Unified model |
| **Migration** | Non-breaking | Requires rewrite |
| **Testing** | Harder (mock wrappers) | Easier (test hooks) |
| **Learning** | Familiar patterns | New mental model |

### Recommendation

**Hooks-as-core is the better long-term architecture**, but it requires:

1. **Breaking change acceptance** - This is a major redesign
2. **Migration path** - Adapters for existing processors/criteria
3. **Documentation** - Clear migration guide
4. **Performance validation** - Benchmarks showing acceptable overhead

If breaking changes are acceptable, proceed with hooks-as-core. If backward compatibility is required, implement hooks as capability first, then migrate core in a future major version.

---

## 8. Implementation Roadmap

### Phase 1: Core Primitives
1. Define `HookPoint` enum
2. Define `Hook` interface and `HookResult`
3. Define `HookContext` with point-specific data
4. Implement `HookRegistry` with priority ordering

### Phase 2: AgentRuntime
1. Implement `AgentRuntime` with hook-driven execution
2. Implement hook execution with signal handling
3. Implement matcher system

### Phase 3: Migration Layer
1. Create `ProcessorHookAdapter` for existing processors
2. Create `CriteriaHookAdapter` for existing criteria
3. Implement `EventEmittingHook` for observability

### Phase 4: AgentBuilder
1. Redesign `AgentBuilder` around hooks
2. Add convenience methods (`beforeToolUse`, `afterToolUse`, etc.)
3. Implement default hooks (replacing base processors)

### Phase 5: Capability Migration
1. Migrate existing capabilities to hook-based
2. Update documentation
3. Provide migration guide

---

## 9. Conclusion

Making hooks a core primitive rather than a capability produces a **cleaner, more unified architecture**:

- **One extension mechanism** instead of five
- **Deterministic control** at every lifecycle point
- **Composable behavior** through priority ordering
- **Natural testing** of isolated hook functions
- **Explicit contracts** for what happens when

The cost is a significant redesign and breaking changes. However, for a new-ish codebase, this investment pays off in long-term maintainability and extensibility.

The resulting API is expressive and intuitive:

```php
$agent = AgentBuilder::new()
    ->withTools($tools)
    ->withDriver($driver)

    // Security
    ->beforeToolUse(new ValidateBashCommands(), toolPattern: 'Bash')
    ->beforeToolUse(new ValidateFilePaths(), toolPattern: 'Read|Write|Edit')

    // Transformation
    ->afterToolUse(new FormatCode(), toolPattern: 'Write|Edit')

    // Control flow
    ->continueWhen(new MaxSteps(20))
    ->continueWhen(new MaxTokens(50000))
    ->continueWhen(new StopOnNoToolCalls())

    // Capability (register their own hooks)
    ->with(new UseTaskPlanning())
    ->with(new UseSelfCritique())

    // Observability
    ->hook(HookPoint::StepEnd, new TelemetryHook())

    ->build();
```

This is the architecture that makes hooks feel **natural and inevitable** rather than **bolted on**.
