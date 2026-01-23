# Hooks as Agent Skeleton

This document explores redesigning the Agent engine with hooks as the foundational architecture rather than a bolted-on capability.

---

## Current Design Inventory

### Mechanisms

| Mechanism | Purpose | How It Works |
|-----------|---------|--------------|
| **Tools** | Agent capabilities | ToolExecutor executes ToolCalls, driver requests them |
| **Processors** | State transformation | Middleware chain wrapping performStep() |
| **Continuation Criteria** | Flow control | Evaluated after each step to decide continue/stop |
| **Events** | Observability | Fire-and-forget dispatch at lifecycle points |
| **onBuild callbacks** | Post-construction modification | Receive built Agent, return modified Agent |

### Capabilities

| Capability | Tools | Processors | Criteria | Events | onBuild |
|------------|-------|------------|----------|--------|---------|
| **UseBash** | BashTool | — | — | — | — |
| **UseFileTools** | Read/Write/Edit/List/Search | — | — | — | — |
| **UseMetadataTools** | MetadataRead/Write/List | PersistMetadataProcessor | — | — | — |
| **UseTaskPlanning** | TodoWriteTool | TodoReminder, TodoRender, PersistTasks | — | — | — |
| **UseSkills** | LoadSkillTool | AppendSkillMetadata | — | — | — |
| **UseStructuredOutputs** | StructuredOutputTool | PersistStructuredOutput | — | — | — |
| **UseSummarization** | — | SummarizeBuffer, MoveMessagesToBuffer | — | — | — |
| **UseSelfCritique** | — | SelfCriticProcessor | SelfCriticContinuationCheck | — | — |
| **UseSubagents** | — | — | — | — | ✓ (adds SpawnSubagentTool) |
| **UseToolRegistry** | ToolsTool | — | — | — | — |

### Benefits of Current Design

1. **Composability** - Capabilities can be mixed and matched
2. **Separation of concerns** - Tools vs processors vs flow control
3. **Type safety** - Typed interfaces for each mechanism
4. **Testability** - Each component can be tested in isolation
5. **Familiarity** - Middleware pattern is well-understood

### Problems with Current Design

1. **Scattered extension points** - To intercept tool execution, you need a processor wrapper + event listeners
2. **No blocking at events** - Events are fire-and-forget, can't prevent actions
3. **Processor-criteria coordination** - SelfCritique needs both, creating coupling
4. **No arg mutation** - Processors see state, not individual tool calls
5. **onBuild is a workaround** - Exists because other mechanisms are insufficient

---

## Hooks-as-Skeleton Design

### Core Insight

Instead of hooks being one of many extension points, hooks ARE the extension points. The agent loop is a skeleton that calls hooks at defined points, and hooks determine everything: tool execution, state changes, continuation, and observation.

### The Agent Loop as Hook Skeleton

```
ExecutionStart
    │
    ▼
┌─────────────────────────────────────┐
│  BeforeStep                         │◄──┐
│      │                              │   │
│      ▼                              │   │
│  BeforeInference                    │   │
│      │                              │   │
│      ▼                              │   │
│  [Driver generates step]            │   │
│      │                              │   │
│      ▼                              │   │
│  AfterInference                     │   │
│      │                              │   │
│      ▼                              │   │
│  ┌─────────────────────────┐        │   │
│  │ For each tool call:     │        │   │
│  │   BeforeToolUse ────────┼───► Can block/modify   │
│  │   [Execute tool]        │        │   │
│  │   AfterToolUse ─────────┼───► Can modify result  │
│  └─────────────────────────┘        │   │
│      │                              │   │
│      ▼                              │   │
│  AfterStep                          │   │
│      │                              │   │
│      ▼                              │   │
│  ShouldContinue ───────────────────►│───┘ (if continue)
│      │                              │
│      ▼ (if stop)                    │
└─────────────────────────────────────┘
    │
    ▼
ExecutionEnd
```

### HookPoint Enum

```php
enum HookPoint: string
{
    // Execution boundaries
    case ExecutionStart = 'execution_start';
    case ExecutionEnd = 'execution_end';

    // Step boundaries
    case BeforeStep = 'before_step';
    case AfterStep = 'after_step';

    // Inference boundaries
    case BeforeInference = 'before_inference';
    case AfterInference = 'after_inference';

    // Tool boundaries
    case BeforeToolUse = 'before_tool_use';
    case AfterToolUse = 'after_tool_use';

    // Flow control
    case ShouldContinue = 'should_continue';

    // Error handling
    case OnError = 'on_error';
}
```

### Hook Contract

```php
interface Hook
{
    /**
     * Which hook points this hook responds to.
     * Return empty array to respond to all points.
     */
    public function points(): array;

    /**
     * Optional matcher for conditional execution.
     */
    public function matcher(): ?HookMatcher;

    /**
     * Execute the hook.
     */
    public function handle(HookContext $context): HookResult;
}
```

### HookResult - The Universal Return Type

```php
final readonly class HookResult
{
    private function __construct(
        public HookDecision $decision,
        public ?string $reason = null,

        // State modifications
        public ?AgentState $state = null,
        public ?array $toolArgs = null,
        public mixed $toolResult = null,

        // Continuation signal (for ShouldContinue)
        public ?bool $shouldContinue = null,

        // Metadata for downstream hooks
        public array $metadata = [],
    ) {}

    // Factory methods
    public static function proceed(): self;                              // Continue normally
    public static function block(string $reason): self;                  // Block the action
    public static function modifyState(AgentState $state): self;         // Mutate state
    public static function modifyArgs(array $args): self;                // Mutate tool args
    public static function modifyResult(mixed $result): self;            // Mutate tool result
    public static function requestContinue(string $reason): self;        // ShouldContinue: yes
    public static function requestStop(string $reason): self;            // ShouldContinue: no
    public static function askUser(string $reason): self;                // Prompt for permission
}
```

### The Hook Engine

```php
final class HookEngine
{
    /** @var array<string, list<Hook>> */
    private array $hooks = [];

    public function register(Hook $hook): void
    {
        foreach ($hook->points() as $point) {
            $this->hooks[$point->value][] = $hook;
        }
    }

    public function run(HookPoint $point, HookContext $context): HookResult
    {
        $hooks = $this->hooks[$point->value] ?? [];
        $aggregatedResult = HookResult::proceed();

        foreach ($this->sortByPriority($hooks) as $hook) {
            if (!$this->matches($hook, $context)) {
                continue;
            }

            $result = $hook->handle($context->with(
                priorResult: $aggregatedResult,
            ));

            $aggregatedResult = $this->aggregate($aggregatedResult, $result);

            if ($result->decision->isTerminal()) {
                break;
            }
        }

        return $aggregatedResult;
    }
}
```

### The New Agent

```php
final class Agent
{
    public function __construct(
        private readonly HookEngine $hooks,
        private readonly CanUseTools $driver,
        private readonly Tools $tools,
    ) {}

    public function run(AgentState $state): AgentState
    {
        // ExecutionStart
        $result = $this->hooks->run(
            HookPoint::ExecutionStart,
            HookContext::forExecutionStart($state)
        );
        $state = $result->state ?? $state;

        // Main loop
        while (true) {
            $state = $this->executeStep($state);

            // ShouldContinue
            $continueResult = $this->hooks->run(
                HookPoint::ShouldContinue,
                HookContext::forShouldContinue($state)
            );

            if ($continueResult->shouldContinue === false) {
                break;
            }
        }

        // ExecutionEnd
        $this->hooks->run(
            HookPoint::ExecutionEnd,
            HookContext::forExecutionEnd($state)
        );

        return $state;
    }

    private function executeStep(AgentState $state): AgentState
    {
        // BeforeStep
        $result = $this->hooks->run(
            HookPoint::BeforeStep,
            HookContext::forBeforeStep($state)
        );
        $state = $result->state ?? $state;

        // BeforeInference
        $result = $this->hooks->run(
            HookPoint::BeforeInference,
            HookContext::forBeforeInference($state)
        );
        $state = $result->state ?? $state;

        // Driver inference
        $step = $this->driver->useTools($state, $this->tools, $this);

        // AfterInference
        $result = $this->hooks->run(
            HookPoint::AfterInference,
            HookContext::forAfterInference($state, $step)
        );
        $state = $result->state ?? $state;

        // Execute tool calls
        foreach ($step->toolCalls() as $toolCall) {
            $state = $this->executeToolCall($state, $toolCall);
        }

        // AfterStep
        $result = $this->hooks->run(
            HookPoint::AfterStep,
            HookContext::forAfterStep($state, $step)
        );

        return $result->state ?? $state;
    }

    private function executeToolCall(AgentState $state, ToolCall $call): AgentState
    {
        // BeforeToolUse
        $result = $this->hooks->run(
            HookPoint::BeforeToolUse,
            HookContext::forBeforeToolUse($state, $call)
        );

        if ($result->decision === HookDecision::Block) {
            return $state->withToolBlocked($call, $result->reason);
        }

        $effectiveCall = $result->toolArgs
            ? $call->withArgs($result->toolArgs)
            : $call;

        // Execute
        $tool = $this->tools->get($effectiveCall->name());
        $execution = $tool->use(...$effectiveCall->args());

        // AfterToolUse
        $result = $this->hooks->run(
            HookPoint::AfterToolUse,
            HookContext::forAfterToolUse($state, $effectiveCall, $execution)
        );

        $effectiveResult = $result->toolResult ?? $execution->result();

        return ($result->state ?? $state)->withToolResult($call, $effectiveResult);
    }
}
```

---

## Capabilities as Hook Providers

Instead of capabilities installing tools + processors + criteria, they provide hooks.

### Interface

```php
interface HookProvider
{
    /**
     * Return hooks this provider contributes.
     */
    public function hooks(): iterable;

    /**
     * Return tools this provider contributes.
     */
    public function tools(): Tools;
}
```

### Mapping Current Capabilities

#### UseBash → BashProvider

```php
final class BashProvider implements HookProvider
{
    public function __construct(
        private readonly BashPolicy $policy,
    ) {}

    public function hooks(): iterable
    {
        // Policy enforcement before bash execution
        yield new class($this->policy) implements Hook {
            public function points(): array {
                return [HookPoint::BeforeToolUse];
            }

            public function matcher(): ?HookMatcher {
                return new ToolNameMatcher('bash');
            }

            public function handle(HookContext $ctx): HookResult {
                $command = $ctx->toolCall->args()['command'] ?? '';
                if (!$this->policy->isAllowed($command)) {
                    return HookResult::block("Command blocked by policy");
                }
                return HookResult::proceed();
            }
        };
    }

    public function tools(): Tools {
        return new Tools(new BashTool($this->policy));
    }
}
```

#### UseTaskPlanning → TaskPlanningProvider

Current implementation needs:
- TodoWriteTool (tool)
- TodoReminderProcessor (processor: injects reminder messages)
- TodoRenderProcessor (processor: renders tasks in context)
- PersistTasksProcessor (processor: extracts tasks to state)

As hooks:

```php
final class TaskPlanningProvider implements HookProvider
{
    public function hooks(): iterable
    {
        // Remind about incomplete tasks (was TodoReminderProcessor)
        yield new class implements Hook {
            public function points(): array {
                return [HookPoint::BeforeStep];
            }

            public function handle(HookContext $ctx): HookResult {
                $tasks = $ctx->state->metadata('tasks');
                if ($tasks?->hasIncomplete()) {
                    $reminder = $this->formatReminder($tasks);
                    return HookResult::modifyState(
                        $ctx->state->withInjectedContext($reminder)
                    );
                }
                return HookResult::proceed();
            }
        };

        // Persist tasks from tool results (was PersistTasksProcessor)
        yield new class implements Hook {
            public function points(): array {
                return [HookPoint::AfterToolUse];
            }

            public function matcher(): ?HookMatcher {
                return new ToolNameMatcher('todo_write');
            }

            public function handle(HookContext $ctx): HookResult {
                $tasks = TaskList::fromToolResult($ctx->toolExecution);
                return HookResult::modifyState(
                    $ctx->state->withMetadata('tasks', $tasks)
                );
            }
        };
    }

    public function tools(): Tools {
        return new Tools(new TodoWriteTool());
    }
}
```

#### UseSelfCritique → SelfCritiqueProvider

Current implementation needs:
- SelfCriticProcessor (evaluates response quality)
- SelfCriticContinuationCheck (requests continuation if not approved)

As hooks:

```php
final class SelfCritiqueProvider implements HookProvider
{
    public function __construct(
        private readonly int $maxIterations = 2,
        private readonly ?string $llmPreset = null,
    ) {}

    public function hooks(): iterable
    {
        // Evaluate response after step (was SelfCriticProcessor)
        yield new class($this->llmPreset) implements Hook {
            public function points(): array {
                return [HookPoint::AfterStep];
            }

            public function handle(HookContext $ctx): HookResult {
                if (!$ctx->step->isFinalResponse()) {
                    return HookResult::proceed();
                }

                $evaluation = $this->evaluate($ctx->state, $ctx->step);
                return HookResult::modifyState(
                    $ctx->state->withMetadata('self_critic', $evaluation)
                );
            }
        };

        // Control continuation (was SelfCriticContinuationCheck)
        yield new class($this->maxIterations) implements Hook {
            public function points(): array {
                return [HookPoint::ShouldContinue];
            }

            public function handle(HookContext $ctx): HookResult {
                $evaluation = $ctx->state->metadata('self_critic');
                $iteration = $ctx->state->metadata('self_critic_iteration', 0);

                if ($evaluation?->approved) {
                    return HookResult::requestStop("Self-critic approved");
                }

                if ($iteration >= $this->maxIterations) {
                    return HookResult::requestStop("Max iterations reached");
                }

                return HookResult::requestContinue(
                    "Self-critic requested revision: " . $evaluation?->feedback
                )->modifyState(
                    $ctx->state->withMetadata('self_critic_iteration', $iteration + 1)
                );
            }
        };
    }

    public function tools(): Tools {
        return new Tools();
    }
}
```

#### UseSummarization → SummarizationProvider

```php
final class SummarizationProvider implements HookProvider
{
    public function hooks(): iterable
    {
        // Check if summarization needed (was SummarizeBuffer processor)
        yield new class($this->policy) implements Hook {
            public function points(): array {
                return [HookPoint::BeforeInference];
            }

            public function handle(HookContext $ctx): HookResult {
                if (!$this->policy->shouldSummarize($ctx->state)) {
                    return HookResult::proceed();
                }

                $summarized = $this->summarize($ctx->state);
                return HookResult::modifyState($summarized);
            }
        };
    }

    public function tools(): Tools {
        return new Tools();
    }
}
```

#### UseSubagents → SubagentProvider

```php
final class SubagentProvider implements HookProvider
{
    public function hooks(): iterable
    {
        // SubagentStop hook - intercept subagent completion
        yield new class implements Hook {
            public function points(): array {
                return [HookPoint::AfterToolUse];
            }

            public function matcher(): ?HookMatcher {
                return new ToolNameMatcher('spawn_subagent');
            }

            public function handle(HookContext $ctx): HookResult {
                // Allow hooks to validate/modify subagent result
                return HookResult::proceed();
            }
        };
    }

    public function tools(): Tools {
        return new Tools(new SpawnSubagentTool(...));
    }
}
```

---

## New AgentBuilder

```php
final class AgentBuilder
{
    private HookEngine $hooks;
    private Tools $tools;
    private ?CanUseTools $driver = null;

    public function __construct()
    {
        $this->hooks = new HookEngine();
        $this->tools = new Tools();

        // Register default hooks
        $this->registerDefaults();
    }

    public function with(HookProvider $provider): self
    {
        foreach ($provider->hooks() as $hook) {
            $this->hooks->register($hook);
        }

        $this->tools = $this->tools->merge($provider->tools());

        return $this;
    }

    public function withHook(Hook $hook): self
    {
        $this->hooks->register($hook);
        return $this;
    }

    public function withDriver(CanUseTools $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    public function build(): Agent
    {
        return new Agent(
            hooks: $this->hooks,
            driver: $this->driver ?? new ToolCallingDriver(),
            tools: $this->tools,
        );
    }

    private function registerDefaults(): void
    {
        // Default continuation: stop when no tool calls
        $this->hooks->register(new ToolCallPresenceHook());

        // Default limits
        $this->hooks->register(new StepLimitHook(20));
        $this->hooks->register(new TokenLimitHook(32768));
        $this->hooks->register(new TimeLimitHook(300));
    }
}
```

### Usage

```php
$agent = AgentBuilder::new()
    ->with(new BashProvider($bashPolicy))
    ->with(new FileToolsProvider($baseDir))
    ->with(new TaskPlanningProvider())
    ->with(new SelfCritiqueProvider(maxIterations: 3))
    ->with(new SubagentProvider($subagentRegistry))
    ->withHook(new CustomLoggingHook())
    ->withHook(new SecurityAuditHook())
    ->build();
```

---

## Comparison: Current vs Hooks-as-Skeleton

| Aspect | Current Design | Hooks-as-Skeleton |
|--------|----------------|-------------------|
| **Extension points** | 5 different mechanisms | 1 unified mechanism |
| **Tool interception** | Not possible directly | BeforeToolUse/AfterToolUse |
| **Flow control** | ContinuationCriteria | ShouldContinue hook |
| **State mutation** | Processors only | Any hook can modify state |
| **Arg mutation** | Not possible | BeforeToolUse can modify args |
| **Blocking** | Not possible | Any Before* hook can block |
| **Observation** | Events (fire-and-forget) | Hooks see everything |
| **Capability pattern** | Tools + Processors + Criteria | HookProvider with unified interface |
| **Learning curve** | Multiple concepts | One concept |

### What We Gain

1. **Simplicity** - One extension mechanism instead of five
2. **Power** - Hooks can do everything processors, criteria, and events could do, plus more
3. **Consistency** - All capabilities follow the same pattern
4. **Interception** - True interception at every point, not just observation
5. **Determinism** - Explicit control over what happens at every lifecycle point

### What We Lose

1. **Familiarity** - Middleware pattern is well-known; hook-driven agents less so
2. **Type specificity** - Processors are typed to state; hooks handle everything
3. **Existing code** - All capabilities need rewriting

### Is It Worth It?

**Yes, if** the primary goal is deterministic control and the codebase is still evolving.

**No, if** the current design is stable and hooks are needed only occasionally.

---

## Hybrid Alternative

If full rewrite is too costly, consider a **thin hook layer** that wraps the existing engine:

```php
final class HookableAgent
{
    public function __construct(
        private readonly Agent $inner,
        private readonly HookEngine $hooks,
    ) {}

    public function run(AgentState $state): AgentState
    {
        // Wrap the inner agent's execution with hooks
        // Hooks call through to inner agent
    }
}
```

This preserves existing capabilities while enabling hook-based extensions.

---

## Recommendation

If starting fresh or early in development: **adopt hooks-as-skeleton**. The unified model is cleaner and more powerful.

If significant code exists: **keep current design + add UseHooks capability** as originally planned. The capability approach is less disruptive and still provides hook functionality where needed.

The key insight remains: hooks provide deterministic lifecycle control that prompting cannot guarantee. Whether they're the skeleton or a capability, that value is preserved.
