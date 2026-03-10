---
title: 'AgentBuilder & Capabilities'
docname: 'agent_builder'
order: 13
id: 'agent-builder'
---

## Introduction

When building agents, you will often find yourself repeating the same configuration: setting up an LLM provider, registering tools, attaching guard hooks, and wiring a message compiler. The `AgentBuilder` class provides a clean composition layer that lets you assemble fully configured `AgentLoop` instances from reusable, self-contained **capabilities**.

Instead of manually constructing each dependency and passing them to `AgentLoop`, you describe what your agent should be able to do by stacking capabilities. Each capability encapsulates a single concern -- configuring the LLM provider, adding a tool, attaching a lifecycle hook, or enabling subagent delegation. The builder composes them all into a working agent in a single `build()` call.

This approach makes agent configuration declarative, testable, and easy to share across your application.

## Quick Start

The following example creates an agent that can execute bash commands, uses the Anthropic provider, and enforces step and token limits:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Polyglot\Inference\LLMProvider;

$agent = AgentBuilder::base()
    ->withCapability(new UseLLMConfig(
        llm: LLMProvider::using('anthropic'),
    ))
    ->withCapability(new UseBash())
    ->withCapability(new UseGuards(maxSteps: 20, maxTokens: 32768))
    ->build();

$state = AgentState::empty()->withUserMessage('List files in /tmp');
$result = $agent->execute($state);
```

The `AgentBuilder::base()` factory creates a builder pre-configured with sensible defaults: a `ToolCallingDriver` backed by the default LLM provider, the `ConversationWithCurrentToolTrace` message compiler, and an empty hook stack. Every `withCapability()` call returns a **new builder instance** -- the builder is immutable, so you can safely branch configurations from a shared base.

## Immutability

`AgentBuilder` is a `final readonly` class. Every `withCapability()` call returns a new builder instance with the capability appended, leaving the original unchanged. This means you can safely branch from a shared base without worrying about mutation side effects:

```php
$base = AgentBuilder::base()
    ->withCapability(new UseLLMConfig(llm: LLMProvider::using('anthropic')))
    ->withCapability(new UseGuards(maxSteps: 20));

// Two agents that share the same LLM config and guards
$coder = $base
    ->withCapability(new UseBash())
    ->withCapability(new UseFileTools('/my/project'))
    ->build();

$reviewer = $base
    ->withCapability(new UseFileTools('/my/project'))
    ->build();
```

## The Build Pipeline

When you call `build()`, the builder delegates to an internal `AgentConfigurator` that resolves all components in a specific order:

1. **Message compiler** -- determines how `AgentState` messages are compiled into the LLM prompt. The default is `ConversationWithCurrentToolTrace`, which includes all non-trace messages plus the current execution's tool traces.
2. **Tool-use driver** -- the driver responsible for calling the LLM and parsing tool calls from the response. The default is `ToolCallingDriver`. The resolved message compiler is injected into the driver at this stage if the driver implements `CanAcceptMessageCompiler`.
3. **Concrete tools** -- all tools registered via capabilities, including deferred tools that need access to the finalized driver or event system. Deferred tools are resolved last because they may depend on the final driver and tool set.
4. **Interceptor** -- the hook stack is compiled into an interceptor that wraps every lifecycle phase of the agent loop. If no hooks have been registered, a lightweight `PassThroughInterceptor` is used instead.

This ordering matters because deferred tools (registered via `UseToolFactory`, `UseSubagents`, or `UsePlanningSubagent`) receive the finalized driver, tool set, and event handler at resolution time. If you need a tool that references the driver, use `UseToolFactory` rather than `UseTools`.

## Core Capabilities

Core capabilities modify fundamental aspects of the agent's behavior: which LLM it talks to, how it handles tool calls, what guards protect execution, and how the conversation context is compiled.

### UseLLMConfig

Configures the LLM provider and creates a `ToolCallingDriver` for the agent. If omitted, the builder uses the default provider from `LLMProvider::new()`.

```php
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;

new UseLLMConfig(
    llm: LLMProvider::using('anthropic'),
    maxRetries: 3,
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `llm` | `LLMProvider\|null` | `null` (uses default) | The LLM provider to use |
| `maxRetries` | `int` | `1` | Maximum inference attempts on transient failure |

When `maxRetries` is greater than 1, an `InferenceRetryPolicy` is created and passed to the driver, enabling automatic retries on transient LLM errors.

### UseGuards

Installs safety guards that stop execution when resource limits are reached. All parameters are optional; set a parameter to `null` to disable that specific guard entirely.

```php
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

new UseGuards(
    maxSteps: 20,            // stop after 20 steps
    maxTokens: 32768,        // stop when cumulative token usage exceeds limit
    maxExecutionTime: 300.0, // stop after 5 minutes of wall-clock time
    finishReasons: [         // stop on specific LLM finish reasons
        InferenceFinishReason::EndTurn,
    ],
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `maxSteps` | `int\|null` | `20` | Maximum number of steps before stopping |
| `maxTokens` | `int\|null` | `32768` | Total token budget across all steps |
| `maxExecutionTime` | `float\|null` | `300.0` | Maximum wall-clock seconds |
| `finishReasons` | `array` | `[]` | Stop when the LLM returns one of these finish reasons |

Guards are implemented as hooks. Step, token, and time guards run at `BeforeStep` with priority `200` (early in the lifecycle). The finish-reason guard runs at `AfterStep` with priority `-200` (late in the lifecycle, after the LLM response has been processed).

### UseTools

Adds one or more tool instances to the agent. Tools are merged with any previously registered tools, never replaced.

```php
use Cognesy\Agents\Capability\Core\UseTools;

new UseTools($searchTool, $calculatorTool)
```

You may call `UseTools` multiple times across different capabilities. Each invocation merges additional tools into the existing set.

### UseHook

Attaches a single hook to the agent's lifecycle. Hooks intercept execution at defined trigger points and can modify the agent state or halt execution entirely.

```php
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Hook\Collections\HookTriggers;

new UseHook(
    hook: $myHook,
    triggers: HookTriggers::afterStep(),
    priority: 10,
    name: 'my_custom_hook',
)
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `hook` | `HookInterface` | (required) | The hook implementation |
| `triggers` | `HookTriggers` | (required) | When the hook fires (e.g., `beforeStep()`, `afterStep()`, `beforeExecution()`) |
| `priority` | `int` | `0` | Higher priority hooks run earlier within the same trigger phase |
| `name` | `string\|null` | `null` | Optional name for debugging and logging |

### UseDriver

Replaces the default tool-use driver entirely. Use this when you need a completely custom driver implementation rather than the default `ToolCallingDriver`.

```php
use Cognesy\Agents\Capability\Core\UseDriver;

new UseDriver($customDriver)
```

### UseDriverDecorator

Wraps the current driver with a decorator function. The decorator receives the existing driver and must return a new `CanUseTools` implementation. This is useful for adding cross-cutting concerns like logging, caching, or rate limiting around the driver without replacing it.

```php
use Cognesy\Agents\Capability\Core\UseDriverDecorator;
use Cognesy\Agents\Drivers\CanUseTools;

new UseDriverDecorator(
    fn(CanUseTools $inner) => new LoggingDriver($inner)
)
```

### UseContextCompiler

Replaces the message compiler that prepares the conversation history for the LLM. The default compiler is `ConversationWithCurrentToolTrace`.

```php
use Cognesy\Agents\Capability\Core\UseContextCompiler;

new UseContextCompiler($customCompiler)
```

### UseContextCompilerDecorator

Wraps the current message compiler with a decorator. This is the recommended approach for adding token-limit trimming, context windowing, or injecting additional context without replacing the entire compilation pipeline.

```php
use Cognesy\Agents\Capability\Core\UseContextCompilerDecorator;
use Cognesy\Agents\Context\CanCompileMessages;

new UseContextCompilerDecorator(
    fn(CanCompileMessages $inner) => new TokenLimitCompiler($inner, maxTokens: 4000)
)
```

### UseContextConfig

Sets a system prompt and optional response format that are injected before each step via a `BeforeStep` hook at priority `100`. The system prompt is always present regardless of how messages are compiled.

```php
use Cognesy\Agents\Capability\Core\UseContextConfig;

new UseContextConfig(
    systemPrompt: 'You are a helpful coding assistant.',
    responseFormat: ['type' => 'json_object'],
)
```

Both a `string` system prompt and a `ResponseFormat` object (or array representation) are accepted. If both are empty, the capability is a no-op.

### UseReActConfig

Replaces the driver with a `ReActDriver` that implements the Reasoning and Acting (ReAct) pattern. The agent alternates between explicit thinking steps and tool-use actions, producing structured reasoning traces that make the decision process transparent.

```php
use Cognesy\Agents\Capability\Core\UseReActConfig;
use Cognesy\Instructor\Enums\OutputMode;

new UseReActConfig(
    inference: $inferenceRuntime,
    structuredOutput: $structuredOutputFactory,
    model: 'gpt-4o',
    maxRetries: 2,
    mode: OutputMode::Json,
)
```

### UseToolFactory

Registers a deferred tool factory. The factory callback is not invoked immediately -- it runs during `build()` after the driver and tool set have been finalized. This gives the factory access to the complete agent context.

```php
use Cognesy\Agents\Capability\Core\UseToolFactory;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Events\Contracts\CanHandleEvents;

new UseToolFactory(
    fn(Tools $tools, CanUseTools $driver, CanHandleEvents $events) =>
        new MyDynamicTool($tools, $driver)
)
```

The callback receives three arguments: the resolved `Tools` collection, the finalized `CanUseTools` driver, and the `CanHandleEvents` event dispatcher. It must return a single `ToolInterface` instance.

## Domain Capabilities

Domain capabilities bundle tools, hooks, and configuration for specific workflows. They are built on top of the core capabilities and provide higher-level abstractions.

| Capability | Location | Description |
|---|---|---|
| `UseBash` | `Capability\Bash` | Adds a bash command execution tool |
| `UseFileTools` | `Capability\File` | Adds file read/write/edit tools scoped to a directory |
| `UseSubagents` | `Capability\Subagent` | Enables spawning child agents from definitions |
| `UsePlanningSubagent` | `Capability\PlanningSubagent` | Adds a planning subagent that creates execution plans |
| `UseStructuredOutputs` | `Capability\StructuredOutput` | Configures structured (typed) output extraction |
| `UseSummarization` | `Capability\Summarization` | Adds conversation summarization hooks |
| `UseSelfCritique` | `Capability\SelfCritique` | Adds self-critique evaluation after responses |
| `UseSkills` | `Capability\Skills` | Injects skill instructions into agent context |
| `UseTaskPlanning` | `Capability\Tasks` | Adds task planning and decomposition tools |
| `UseMetadataTools` | `Capability\Metadata` | Adds tools for reading/writing agent metadata |
| `UseToolRegistry` | `Capability\Tools` | Resolves tools from a named registry at build time |
| `UseExecutionHistory` | `Capability\ExecutionHistory` | Tracks and exposes execution history |
| `UseExecutionRetrospective` | `Capability\Retrospective` | Adds retrospective analysis of past executions |

## Writing Custom Capabilities

Every capability implements the `CanProvideAgentCapability` interface, which defines two methods: `capabilityName()` for registry lookups, and `configure()` for applying the capability's configuration to the agent.

```php
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;

final readonly class UseRateLimiting implements CanProvideAgentCapability
{
    public function __construct(
        private int $maxCallsPerMinute,
    ) {}

    public static function capabilityName(): string {
        return 'use_rate_limiting';
    }

    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $driver = $agent->toolUseDriver();

        return $agent->withToolUseDriver(
            new RateLimitedDriver($driver, $this->maxCallsPerMinute)
        );
    }
}
```

The `CanConfigureAgent` interface provides read and write access to all configurable components:

| Method | Returns | Purpose |
|---|---|---|
| `tools()` / `withTools()` | `Tools` | Registered tool instances |
| `contextCompiler()` / `withContextCompiler()` | `CanCompileMessages` | Message compilation strategy |
| `toolUseDriver()` / `withToolUseDriver()` | `CanUseTools` | LLM driver for tool calling |
| `hooks()` / `withHooks()` | `HookStack` | Execution lifecycle hooks |
| `deferredTools()` / `withDeferredTools()` | `DeferredToolProviders` | Tools resolved at build time |
| `events()` | `CanHandleEvents` | Event dispatcher (read-only) |

The `capabilityName()` method returns a string identifier used by `AgentCapabilityRegistry` to look up capabilities by name. This is how agent templates reference capabilities in their definition files (see [Agent Templates](14-agent-templates.md)).

## AgentBuilder vs AgentLoop

Use `AgentLoop::default()` or construct `AgentLoop` directly when you have a small, one-off setup where the wiring is straightforward. Use `AgentBuilder` when:

- The configuration is complex enough to benefit from decomposition into capabilities.
- You want to share the same setup across multiple agents via branching.
- You need deferred tool resolution (tools that depend on the final driver).
- You want testable, reusable configuration units that can be independently verified.

## Event Propagation

The `AgentBuilder::base()` method accepts an optional `CanHandleEvents` parent. Events dispatched by the built agent propagate upward to this parent handler, allowing you to collect events from multiple agents in a single place:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;

$rootEvents = new EventDispatcher('root');
$rootEvents->wiretap(fn($event) => logger()->debug((string) $event));

$agent = AgentBuilder::base(parentEvents: $rootEvents)
    ->withCapability(new UseBash())
    ->build();
```

This is particularly useful when running subagents or sessions, where you want a unified event stream across all agent activity.

## Related

- [Basic Agent](02-basic-agent.md)
- [Hooks](08-hooks.md)
- [Agent Templates](14-agent-templates.md)
- [Subagents](15-subagents.md)
- [Session Runtime](16-session-runtime.md)
