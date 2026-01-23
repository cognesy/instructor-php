# Plan: Hook System via Middleware Pattern

## Requirements (Clarified)

1. **No subclassing** - Hooks must be composable without extending classes
2. **Multiple hooks per point** - Stack handlers at each lifecycle point
3. **Ordering control** - Priority-based or dependency-based (`before('name')`, `after('name')`)
4. **Middleware pattern** - Proven, flexible, follows existing StateProcessors
5. **Syntactic sugar** - Convenience methods like `onBeforeToolCall(callable)`
6. **Filters/matchers** - Conditional hook execution (e.g., only for specific tools)

---

## Design: Middleware-Based Hooks

### Core Insight

We already have StateProcessors (middleware for state). Extend this pattern to:
1. **ToolMiddleware** - Intercept individual tool calls
2. **Named hook points** - Convenience layer over middleware registration

### Architecture

```
┌─────────────────────────────────────────────────────────┐
│  AgentBuilder                                           │
│                                                         │
│  onBeforeStep(callable, priority, matcher)              │
│  onAfterStep(callable, priority, matcher)               │
│  onBeforeToolUse(callable, priority, matcher)           │
│  onAfterToolUse(callable, priority, matcher)            │
│  onShouldContinue(callable, priority)                   │
│                                                         │
│          │                    │                         │
│          ▼                    ▼                         │
│  ┌──────────────┐    ┌──────────────────┐              │
│  │ StepHooks    │    │ ToolMiddleware   │              │
│  │ (Processors) │    │ Stack            │              │
│  └──────────────┘    └──────────────────┘              │
│          │                    │                         │
│          ▼                    ▼                         │
│  ┌──────────────┐    ┌──────────────────┐              │
│  │ Agent        │───►│ ToolExecutor     │              │
│  │ (uses procs) │    │ (uses middleware)│              │
│  └──────────────┘    └──────────────────┘              │
└─────────────────────────────────────────────────────────┘
```

---

## 1. ToolMiddleware Stack

### Interface

```php
interface ToolMiddleware
{
    public function process(
        ToolCall $call,
        AgentState $state,
        callable $next
    ): AgentExecution;
}
```

### Stack Implementation

```php
final class ToolMiddlewareStack
{
    /** @var list<ToolMiddleware> */
    private array $middleware = [];

    public function push(ToolMiddleware $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function process(
        ToolCall $call,
        AgentState $state,
        callable $executor
    ): AgentExecution {
        $chain = $this->buildChain($executor);
        return $chain($call, $state);
    }

    private function buildChain(callable $executor): callable
    {
        $next = $executor;

        foreach (array_reverse($this->middleware) as $middleware) {
            $currentNext = $next;
            $next = fn($call, $state) => $middleware->process($call, $state, $currentNext);
        }

        return $next;
    }
}
```

### Integration with ToolExecutor

```php
final class ToolExecutor implements CanExecuteToolCalls
{
    public function __construct(
        private readonly Tools $tools,
        private readonly ToolMiddlewareStack $middleware,
        // ...
    ) {}

    public function useTool(ToolCall $call, AgentState $state): AgentExecution
    {
        return $this->middleware->process(
            $call,
            $state,
            fn($c, $s) => $this->executeDirectly($c, $s)
        );
    }

    private function executeDirectly(ToolCall $call, AgentState $state): AgentExecution
    {
        // Current implementation moved here
    }
}
```

---

## 2. Convenience Middleware (Syntactic Sugar)

### Before/After Callable Wrapper

```php
final class BeforeToolMiddleware implements ToolMiddleware
{
    public function __construct(
        private readonly Closure $callback,
        private readonly ?HookMatcher $matcher = null,
    ) {}

    public function process(ToolCall $call, AgentState $state, callable $next): AgentExecution
    {
        if ($this->matcher && !$this->matcher->matches($call, $state)) {
            return $next($call, $state);
        }

        // Callback can return:
        // - null → block the call
        // - ToolCall → proceed with (possibly modified) call
        // - void/same call → proceed unchanged
        $result = ($this->callback)($call, $state);

        if ($result === null) {
            return AgentExecution::blocked($call, 'Blocked by hook');
        }

        $effectiveCall = $result instanceof ToolCall ? $result : $call;
        return $next($effectiveCall, $state);
    }
}

final class AfterToolMiddleware implements ToolMiddleware
{
    public function __construct(
        private readonly Closure $callback,
        private readonly ?HookMatcher $matcher = null,
    ) {}

    public function process(ToolCall $call, AgentState $state, callable $next): AgentExecution
    {
        $execution = $next($call, $state);

        if ($this->matcher && !$this->matcher->matches($call, $state)) {
            return $execution;
        }

        // Callback can modify execution result
        $result = ($this->callback)($execution, $call, $state);

        return $result instanceof AgentExecution ? $result : $execution;
    }
}
```

### Matchers

```php
interface HookMatcher
{
    public function matches(ToolCall $call, AgentState $state): bool;
}

final class ToolNameMatcher implements HookMatcher
{
    public function __construct(
        private readonly string $pattern,  // 'bash', 'read_*', '/^write_.+/'
    ) {}

    public function matches(ToolCall $call, AgentState $state): bool
    {
        $name = $call->name();

        // Exact match
        if ($this->pattern === $name || $this->pattern === '*') {
            return true;
        }

        // Wildcard (simple glob)
        if (str_contains($this->pattern, '*')) {
            $regex = '/^' . str_replace('*', '.*', preg_quote($this->pattern, '/')) . '$/';
            return (bool) preg_match($regex, $name);
        }

        // Regex (starts with /)
        if (str_starts_with($this->pattern, '/')) {
            return (bool) preg_match($this->pattern, $name);
        }

        return false;
    }
}
```

---

## 3. Ordering: Priority-Based

### Simple Approach: Integer Priorities

```php
final class PrioritizedMiddlewareStack
{
    /** @var SplPriorityQueue<ToolMiddleware> */
    private SplPriorityQueue $queue;

    public function add(ToolMiddleware $middleware, int $priority = 0): self
    {
        $this->queue->insert($middleware, $priority);
        return $this;
    }
}
```

Higher priority = runs earlier (wraps outer).

### Alternative: Named Dependencies

```php
final class HookRegistration
{
    public function __construct(
        public readonly string $name,
        public readonly ToolMiddleware $middleware,
        public readonly array $before = [],  // Run before these hooks
        public readonly array $after = [],   // Run after these hooks
    ) {}
}

// Usage:
$builder->onBeforeToolUse(
    name: 'security_check',
    callback: fn($call) => $this->validateSecurity($call),
);

$builder->onBeforeToolUse(
    name: 'logging',
    callback: fn($call) => $this->log($call),
    after: ['security_check'],  // Logging runs after security
);
```

**Recommendation:** Start with priority-based (simpler), add named dependencies if needed.

---

## 4. AgentBuilder Integration

```php
final class AgentBuilder
{
    private ToolMiddlewareStack $toolMiddleware;
    private array $stepPreProcessors = [];
    private array $stepPostProcessors = [];

    // Tool hooks
    public function onBeforeToolUse(
        callable $callback,
        int $priority = 0,
        string|HookMatcher|null $matcher = null,
    ): self {
        $matcherObj = $this->resolveMatcher($matcher);
        $middleware = new BeforeToolMiddleware(Closure::fromCallable($callback), $matcherObj);
        $this->toolMiddleware->add($middleware, $priority);
        return $this;
    }

    public function onAfterToolUse(
        callable $callback,
        int $priority = 0,
        string|HookMatcher|null $matcher = null,
    ): self {
        $matcherObj = $this->resolveMatcher($matcher);
        $middleware = new AfterToolMiddleware(Closure::fromCallable($callback), $matcherObj);
        $this->toolMiddleware->add($middleware, $priority);
        return $this;
    }

    // Step hooks (use existing processor mechanism)
    public function onBeforeStep(callable $callback, int $priority = 0): self
    {
        $this->stepPreProcessors[] = new CallableProcessor(
            Closure::fromCallable($callback),
            $priority,
            position: 'before',
        );
        return $this;
    }

    public function onAfterStep(callable $callback, int $priority = 0): self
    {
        $this->stepPostProcessors[] = new CallableProcessor(
            Closure::fromCallable($callback),
            $priority,
            position: 'after',
        );
        return $this;
    }

    private function resolveMatcher(string|HookMatcher|null $matcher): ?HookMatcher
    {
        if ($matcher === null) return null;
        if ($matcher instanceof HookMatcher) return $matcher;
        return new ToolNameMatcher($matcher);  // String = tool name pattern
    }

    public function build(): Agent
    {
        $toolExecutor = new ToolExecutor(
            tools: $this->tools,
            middleware: $this->toolMiddleware,
        );

        // Merge step hooks into processors
        $processors = $this->buildProcessors();

        return new Agent(
            tools: $this->tools,
            toolExecutor: $toolExecutor,
            processors: $processors,
            // ...
        );
    }
}
```

---

## 5. Usage Examples

```php
$agent = AgentBuilder::new()
    // Block dangerous bash commands
    ->onBeforeToolUse(
        callback: function (ToolCall $call, AgentState $state): ?ToolCall {
            $command = $call->args()['command'] ?? '';
            if (str_contains($command, 'rm -rf')) {
                return null;  // Block
            }
            return $call;
        },
        matcher: 'bash',
        priority: 100,  // High priority = runs first
    )

    // Log all tool calls
    ->onBeforeToolUse(
        callback: function (ToolCall $call) {
            $this->logger->info("Tool call: {$call->name()}");
        },
        priority: -100,  // Low priority = runs last (closest to execution)
    )

    // Modify results
    ->onAfterToolUse(
        callback: function (AgentExecution $exec, ToolCall $call, AgentState $state) {
            // Add metadata to result
            return $exec->withMetadata(['logged_at' => time()]);
        },
    )

    // Step-level hooks
    ->onBeforeStep(function (AgentState $state) {
        return $state->withMetadata('step_started', microtime(true));
    })

    ->onAfterStep(function (AgentState $state) {
        $duration = microtime(true) - $state->metadata('step_started');
        $this->metrics->recordStepDuration($duration);
        return $state;
    })

    ->build();
```

---

## Files to Create/Modify

### New Files
1. `packages/addons/src/Agent/Core/Middleware/ToolMiddleware.php` - Interface
2. `packages/addons/src/Agent/Core/Middleware/ToolMiddlewareStack.php` - Stack
3. `packages/addons/src/Agent/Core/Middleware/BeforeToolMiddleware.php` - Before wrapper
4. `packages/addons/src/Agent/Core/Middleware/AfterToolMiddleware.php` - After wrapper
5. `packages/addons/src/Agent/Core/Middleware/HookMatcher.php` - Matcher interface
6. `packages/addons/src/Agent/Core/Middleware/ToolNameMatcher.php` - Tool name matcher
7. `packages/addons/src/StepByStep/StateProcessing/Processors/CallableProcessor.php` - Callable wrapper

### Modified Files
1. `packages/addons/src/Agent/Core/ToolExecutor.php` - Accept middleware stack
2. `packages/addons/src/AgentBuilder/AgentBuilder.php` - Add hook registration methods

---

## Verification

1. **Unit tests** for ToolMiddlewareStack chain building
2. **Unit tests** for BeforeToolMiddleware blocking/modification
3. **Unit tests** for AfterToolMiddleware result modification
4. **Unit tests** for ToolNameMatcher patterns
5. **Integration test** for full hook flow through AgentBuilder
6. **Backward compatibility** - Existing tests pass without middleware

---

## Open Questions (Resolved)

| Question | Resolution |
|----------|------------|
| Subclassing vs composition? | **Composition** via middleware |
| Ordering mechanism? | **Priority-based** (integer), named deps later if needed |
| Tool-level interception? | **ToolMiddleware** stack |
| Step-level interception? | **Existing processors** with callable wrapper |
| Syntactic sugar? | **`onBeforeToolUse(callback, priority, matcher)`** |

---

## Summary

Extend the proven middleware pattern:
- **Tool level**: New `ToolMiddleware` stack in `ToolExecutor`
- **Step level**: Existing `StateProcessors` with callable wrapper
- **Registration**: `AgentBuilder.onBeforeToolUse()`, `onAfterToolUse()`, etc.
- **Ordering**: Priority-based (higher = outer)
- **Filtering**: Matcher pattern (string → ToolNameMatcher, or custom HookMatcher)

This provides full hook functionality while maintaining the flexibility and composability of middleware.
