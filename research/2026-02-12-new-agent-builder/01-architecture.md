# Target Architecture

## Three-Layer Model

```
AgentState          — all data, context, messages, metadata
AgentLoop           — stateless execution engine
AgentBuilder        — composition engine (HookStack + Tools + Driver → AgentLoop)
Use* Capabilities   — packaged features that install tools + hooks + config
```

Each layer has a sharp, non-overlapping responsibility:

- **AgentState** owns all mutable data: messages, system prompt, metadata, execution state
- **AgentLoop** owns the step loop: iterate, delegate to interceptor/driver/executor, emit events
- **AgentBuilder** owns composition: accept capabilities, wire HookStack, resolve tool factories, build AgentLoop
- **Capabilities** own domain features: what tools, what hooks, what policies, at what priorities

## Target AgentBuilder — Stripped Core

```php
final class AgentBuilder
{
    private Tools $tools;
    private HookStack $hookStack;
    private CanHandleEvents $events;
    private ?CanUseTools $driver = null;
    private ?CanCompileMessages $contextCompiler = null;

    /** @var array<callable(Tools, CanUseTools, CanHandleEvents): BaseTool> */
    private array $toolFactories = [];

    private function __construct() {
        $this->tools = new Tools();
        $this->events = new EventDispatcher('agent-builder');
        $this->hookStack = new HookStack(new RegisteredHooks());
    }

    public static function base(): self {
        return new self();
    }

    // === Core API — nothing else ===

    public function withCapability(AgentCapability $capability): self {
        $capability->install($this);
        return $this;
    }

    public function withTools(Tools|array $tools): self {
        // merge into $this->tools
    }

    public function addToolFactory(callable $factory): self {
        $this->toolFactories[] = $factory;
        return $this;
    }

    public function addHook(
        HookInterface $hook,
        HookTriggers $triggers,
        int $priority = 0,
        ?string $name = null,
    ): self {
        $this->hookStack = $this->hookStack->with($hook, $triggers, $priority, $name);
        return $this;
    }

    public function withDriver(CanUseTools $driver): self {
        $this->driver = $driver;
        return $this;
    }

    public function withContextCompiler(CanCompileMessages $compiler): self {
        $this->contextCompiler = $compiler;
        return $this;
    }

    public function contextCompiler(): ?CanCompileMessages {
        return $this->contextCompiler;
    }

    public function withEvents(CanHandleEvents $events): self {
        $this->events = new EventDispatcher('agent-builder', $events);
        return $this;
    }

    public function eventHandler(): CanHandleEvents {
        return $this->events;
    }

    public function hookStack(): HookStack {
        return $this->hookStack;
    }

    // === Build ===

    public function build(): AgentLoop {
        $compiler = $this->contextCompiler ?? new ConversationWithCurrentToolTrace();
        $driver = $this->resolveDriver($compiler);
        // ... resolve tool factories, snapshot hook stack, wire executor
        return new AgentLoop($tools, $toolExecutor, $driver, $this->events, $interceptor);
    }

    private function resolveDriver(CanCompileMessages $compiler): CanUseTools {
        if ($this->driver !== null) {
            // Apply compiler to existing driver if it supports it
            return match (true) {
                $this->driver instanceof CanAcceptMessageCompiler
                    => $this->driver->withMessageCompiler($compiler),
                default => $this->driver,
            };
        }

        return new ToolCallingDriver(
            llm: LLMProvider::new(),
            messageCompiler: $compiler,
            events: $this->events,
        );
    }
}
```

**Context compiler as a core primitive.** The context compiler controls how messages are prepared before each LLM call. Unlike LLM preset or retry policy (which configure the inference provider), the compiler is a cross-cutting concern — multiple capabilities may need to influence it. For example, a token-budget capability might wrap the existing compiler with truncation logic, while a retrieval-augmented capability might wrap it to inject retrieved context.

By exposing `contextCompiler()` as a getter, capabilities can read-then-wrap:

```php
// Inside a capability's install():
$inner = $builder->contextCompiler() ?? new ConversationWithCurrentToolTrace();
$builder->withContextCompiler(new TokenBudgetCompiler($inner, $this->maxTokens));
```

The compiler is resolved during `build()` and applied to the driver. This keeps it independent of driver construction — a capability can set the compiler without knowing or caring how the driver is configured.

### What was removed from AgentBuilder

| Removed property/method | Moves to |
|---|---|
| `$maxSteps`, `withMaxSteps()` | `UseGuards` capability |
| `$maxTokens`, `withMaxTokens()` | `UseGuards` capability |
| `$maxExecutionTime`, `withTimeout()` | `UseGuards` capability |
| `$maxRetries`, `withMaxRetries()` | `UseLlmConfig` capability |
| `$finishReasons`, `withFinishReasons()` | `UseGuards` or dedicated capability |
| `$systemPrompt`, `withSystemPrompt()` | `UseContextConfig` capability |
| `$responseFormat`, `withResponseFormat()` | `UseContextConfig` capability |
| `$llmPreset`, `withLlmPreset()` | `UseLlmConfig` capability |
| `$contextCompiler`, `withContextCompiler()` | Stays on builder (core primitive — see below) |
| `addGuardHooks()` (private) | `UseGuards::install()` |
| `addContextHooks()` (private) | `UseContextConfig::install()` |
| `addMessageHooks()` (private) | Removed or folded into relevant capability |

### What stays on AgentBuilder

Only the composition primitives:
- `withCapability()` — install packaged features
- `withTools()` — register tools directly
- `addToolFactory()` — deferred tool creation
- `addHook()` — register hooks directly
- `withDriver()` — set inference driver
- `withContextCompiler()` / `contextCompiler()` — set/get message compiler (wrappable by capabilities)
- `withEvents()` — set event handler
- `build()` — produce AgentLoop

These are the universal composition operations that every capability uses via `install()`. They're the builder's vocabulary — not its opinions.

## AgentLoop — Unchanged

AgentLoop stays exactly as-is. It is already at its irreducible size:
- The step loop (`iterate`/`execute`)
- Lifecycle dispatch to interceptor
- Tool use delegation to driver
- Event emission
- Immutable `with*` mutators for standalone use

No guards, no system prompt, no driver construction logic beyond `default()`.

## Relationship Diagram

```
User code
  │
  ├─ Simple case: AgentLoop::default()->withTool(...)
  │    └─ No builder needed. Stateless engine with tools.
  │
  └─ Rich case: AgentBuilder::base()
       │   ->withCapability(new UseGuards(...))
       │   ->withCapability(new UseContextConfig(...))
       │   ->withCapability(new UseBash(...))
       │   ->withCapability(new UseSubagents(...))
       │   ->build()
       │
       ├── Each capability calls:
       │     builder->withTools(...)
       │     builder->addHook(...)
       │     builder->addToolFactory(...)
       │     builder->withContextCompiler(...)  // can read + wrap existing
       │     builder->withDriver(...)
       │
       └── build() resolves:
             compiler → final CanCompileMessages (or default)
             driver → apply compiler to driver (or build default with compiler)
             factories → Tools (receive resolved driver)
             hooks → HookStack (snapshot)
             → new AgentLoop(tools, executor, driver, events, hookStack)
```
