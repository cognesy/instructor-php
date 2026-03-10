---
title: 'Tools'
description: 'Register and use tools that let agents take actions based on LLM decisions'
---

# Tools

Tools are the primary mechanism through which an agent interacts with the outside world. When you give an agent a set of tools, the LLM decides which tool to call, with what arguments, and when. The agent loop orchestrates this cycle automatically: the LLM requests a tool call, the framework executes it, feeds the result back, and the LLM continues reasoning until it produces a final response.

This page covers the full tool API -- from creating and registering tools, through the contracts that govern them, to the execution lifecycle and error handling. For practical step-by-step guidance on building your own tools, see [Building Tools](06-building-tools.md).

<a name="creating-tools-with-functiontool"></a>
## Creating Tools With FunctionTool

The fastest way to create a tool is to wrap any PHP callable with `FunctionTool::fromCallable()`. The tool name, description, and parameter schema are all generated automatically from the function signature using reflection:

```php
use Cognesy\Agents\Tool\Tools\FunctionTool;
use Cognesy\Schema\Attributes\Description;

#[Description('Look up the current weather for a given city')]
function get_weather(
    #[Description('City name, e.g. "Paris"')] string $city,
): string {
    return "Weather in {$city}: 72F, sunny";
}

$tool = FunctionTool::fromCallable(get_weather(...));
```

The `#[Description]` attribute on the function provides the tool description that the LLM sees. The same attribute on parameters documents individual arguments in the generated JSON schema. Named functions produce meaningful tool names; closures work too, but you should prefer named functions for clarity.

> **Tip:** `FunctionTool` is the recommended starting point for most projects. It handles schema generation, argument passing, and result wrapping with zero boilerplate.

<a name="the-tools-collection"></a>
## The Tools Collection

Tools are collected in the immutable `Tools` value object. Pass any number of `ToolInterface` implementations to its constructor, and the collection indexes them by name:

```php
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Tool\Tools\FunctionTool;

function get_weather(string $city): string {
    return "Weather in {$city}: 72F, sunny";
}

$tools = new Tools(
    FunctionTool::fromCallable(get_weather(...)),
);
```

### Querying the Collection

The `Tools` collection provides a rich query API for inspecting registered tools at runtime:

```php
$tools->has('get_weather');   // bool -- check if a tool is registered
$tools->get('get_weather');   // ToolInterface -- retrieve by name (throws if missing)
$tools->names();              // ['get_weather', ...] -- all registered names
$tools->count();              // int -- number of tools
$tools->isEmpty();            // bool -- true when collection is empty
$tools->all();                // array<string, ToolInterface> -- keyed by name
$tools->descriptions();       // [['name' => ..., 'description' => ...], ...]
$tools->toToolSchema();       // JSON schema array sent to the LLM
```

The `descriptions()` method returns an array of compact summaries (name and description) for each tool. The `toToolSchema()` method returns the full OpenAI-compatible function-calling schema array that gets sent to the LLM as part of the inference request.

### Immutable Mutators

The `Tools` collection is immutable. Every mutation returns a new instance, leaving the original unchanged:

```php
// Add a single tool
$tools = $tools->withTool($anotherTool);

// Add multiple tools at once
$tools = $tools->withTools($toolA, $toolB, $toolC);

// Remove a tool by name
$tools = $tools->withToolRemoved('get_weather');

// Merge two collections (tools from $other override same-named tools)
$tools = $tools->merge($otherToolsCollection);
```

<a name="multiple-tools"></a>
## Registering Multiple Tools

Pass multiple tools to the `Tools` constructor. The LLM chooses which tool to call on each turn:

```php
use Cognesy\Agents\Tool\Tools\FunctionTool;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Schema\Attributes\Description;

#[Description('Get the current weather for a city')]
function get_weather(
    #[Description('City name')] string $city,
): string {
    return "Weather in {$city}: 72F, sunny";
}

#[Description('Evaluate a math expression')]
function calculate(
    #[Description('Math expression to evaluate')] string $expression,
): string {
    return (string) eval("return {$expression};");
}

$tools = new Tools(
    FunctionTool::fromCallable(get_weather(...)),
    FunctionTool::fromCallable(calculate(...)),
);
```

<a name="attaching-tools-to-an-agent"></a>
## Attaching Tools to an Agent

There are two ways to give tools to an agent: directly on the `AgentLoop`, or through the `AgentBuilder` capability system.

### Direct Assignment

The `AgentLoop` provides `withTools()` (replacing the entire collection) and `withTool()` (appending a single tool) methods:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;

$loop = AgentLoop::default()->withTools($tools);

$state = AgentState::empty()->withUserMessage('What is the weather in Paris?');
$result = $loop->execute($state);

echo $result->finalResponse()->toString();
```

You can also add tools one at a time:

```php
$loop = AgentLoop::default()
    ->withTool(FunctionTool::fromCallable(get_weather(...)))
    ->withTool(FunctionTool::fromCallable(calculate(...)));
```

### Via the AgentBuilder

The `UseTools` capability integrates tools through the builder's composition layer. This is the preferred approach when assembling agents from reusable capabilities:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseTools;

$loop = AgentBuilder::base()
    ->withCapability(new UseTools(
        FunctionTool::fromCallable(get_weather(...)),
        FunctionTool::fromCallable(calculate(...)),
    ))
    ->build();
```

`UseTools` merges the provided tools into any tools already registered on the builder, so you can combine multiple `UseTools` capabilities without overwriting earlier registrations.

<a name="tool-contracts"></a>
## Tool Contracts

The tool system is built on a small set of interfaces. Understanding them helps when you need to go beyond the basics and build custom tool implementations.

### ToolInterface

Every tool implements `ToolInterface`, which defines the three things the framework needs from a tool:

```php
interface ToolInterface {
    public function use(mixed ...$args): Result;         // Execute the tool
    public function toToolSchema(): ToolDefinition;      // Schema sent to the LLM
    public function descriptor(): CanDescribeTool;       // Metadata accessor
}
```

The `use()` method receives the arguments that the LLM provided and returns a `Result` object wrapping either a success value or a failure. The `toToolSchema()` method returns a `ToolDefinition` value object describing the tool's name, description, and parameters. The `descriptor()` method returns the tool's identity and documentation.

### CanDescribeTool

The descriptor interface provides identity and documentation at two levels of detail:

```php
interface CanDescribeTool {
    public function name(): string;          // Tool name (e.g., 'read_file')
    public function description(): string;   // What the tool does
    public function metadata(): array;       // Lightweight info for browsing/discovery
    public function instructions(): array;   // Full specification with parameters
}
```

**`metadata()`** returns a compact summary suitable for listing tools. The default implementation includes `name` and `summary` keys, with an optional `namespace` key for namespaced tool names (e.g., `file.read` yields namespace `file`).

**`instructions()`** returns the complete specification including parameter definitions and return type. This two-level design supports tool registries where an agent can browse available tools before loading their full documentation.

### CanAccessAgentState

Tools that need to read the current agent execution state implement `CanAccessAgentState`. The framework calls `withAgentState()` before each invocation, passing in the current `AgentState`. The method returns a new (cloned) instance with the state injected:

```php
interface CanAccessAgentState {
    public function withAgentState(AgentState $state): static;
}
```

State is read-only from the tool's perspective. The `withAgentState()` method clones the tool and injects the state, ensuring that tool instances remain safe to reuse across invocations. Modifications to agent state should be handled by the agent's state processors, not by tools directly.

### CanAccessToolCall

Tools that need access to their invocation context (the raw `ToolCall` object with its ID and arguments) implement `CanAccessToolCall`. This is useful for correlation, tracing, logging, and subagent tools that emit events:

```php
interface CanAccessToolCall {
    public function withToolCall(ToolCall $toolCall): static;
}
```

Like `CanAccessAgentState`, this method clones the tool and injects the `ToolCall`, preserving immutability.

### CanManageTools

The `CanManageTools` interface defines the contract for mutable tool registries that support lazy instantiation through factories:

```php
interface CanManageTools {
    public function register(ToolInterface $tool): void;
    public function registerFactory(string $name, callable $factory): void;
    public function has(string $name): bool;
    public function get(string $name): ToolInterface;
    public function all(): array;
    public function names(): array;
    public function count(): int;
}
```

The `ToolRegistry` class implements this interface and is used internally by the `ToolsTool` capability for dynamic tool discovery. The `registerFactory()` method accepts a `callable(): ToolInterface` that is only invoked when the tool is first requested, enabling lazy loading of expensive tools.

### CanExecuteToolCalls

The `CanExecuteToolCalls` interface defines the contract for executing a batch of tool calls against a given agent state:

```php
interface CanExecuteToolCalls {
    public function executeTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}
```

The `ToolExecutor` class is the default implementation, and the `AgentLoop` accepts a custom executor via `withToolExecutor()`.

<a name="class-hierarchy"></a>
## The Tool Class Hierarchy

The framework provides a layered set of abstract base classes. Each layer adds a specific concern, so you can extend at the level of abstraction that fits your use case:

| Class | What it adds | When to use |
|---|---|---|
| `SimpleTool` | Descriptor + result wrapper + `$this->arg()` helper | Full manual control over everything |
| `ReflectiveSchemaTool` | Auto-generates `toToolSchema()` via reflection | When you want schema from `__invoke` signature |
| `FunctionTool` | Wraps a callable with cached reflective schema | Typed callable tools (most common) |
| `StateAwareTool` | `withAgentState()` / `$this->agentState` | When you need to read execution state |
| `BaseTool` | State + reflective schema + default metadata/instructions | State-aware class-based tools |
| `ContextAwareTool` | State + `withToolCall()` / `$this->toolCall` | When you need raw tool call context |

The inheritance chain flows as follows:

```
SimpleTool                              # descriptor, result wrapping, arg()
  +-- ReflectiveSchemaTool              # auto toToolSchema() from __invoke
  |     +-- FunctionTool                # wraps callable + cached schema
  +-- StateAwareTool                    # + CanAccessAgentState
        +-- BaseTool                    # + reflective schema + metadata/instructions
        +-- ContextAwareTool            # + CanAccessToolCall
```

For most projects, `FunctionTool` or `BaseTool` is all you need. See [Building Tools](06-building-tools.md) for practical guidance, and [Building Tools: Advanced Patterns](17-building-tools-advanced.md) for lower-level patterns.

<a name="tool-execution-lifecycle"></a>
## How Tool Execution Works

The `ToolExecutor` manages the full lifecycle of a tool call. Understanding this flow helps when debugging or customizing tool behavior.

### 1. Schema Delivery

The `Tools` collection serializes all tool schemas via `toToolSchema()` and sends them to the LLM as part of the inference request. Each schema follows the OpenAI function-calling format:

```php
[
    'type' => 'function',
    'function' => [
        'name' => 'get_weather',
        'description' => 'Get the current weather for a city',
        'parameters' => [
            'type' => 'object',
            'properties' => [...],
            'required' => [...],
        ],
    ],
]
```

### 2. Tool Call Parsing

When the LLM responds with one or more tool calls, the framework parses them into `ToolCall` objects containing the tool name, call ID, and arguments.

### 3. Hook Interception (Before)

Before executing each tool call, the `ToolExecutor` runs the `beforeToolUse` lifecycle hook via the interceptor. Hooks can:

- **Modify the tool call** (e.g., rewrite arguments).
- **Modify the agent state** (e.g., inject context).
- **Block execution entirely** by marking the hook context as blocked. When blocked, a `ToolExecution::blocked()` result is returned without invoking the tool.

If the `stopOnToolBlock` option is enabled on the `ToolExecutor`, the entire batch stops after the first blocked tool call.

### 4. Tool Preparation

The executor looks up the tool by name from the `Tools` collection. If the tool implements `CanAccessAgentState`, a clone with the current `AgentState` injected is created. If it implements `CanAccessToolCall`, the raw `ToolCall` is injected the same way. This ensures tools are stateless and safe for concurrent use.

### 5. Argument Validation

Required parameters declared in the tool's schema are checked against the provided arguments. Missing required parameters produce a `Result::failure()` with an `InvalidToolArgumentsException` without invoking the tool. The LLM sees the error message and can retry with corrected arguments.

### 6. Execution

The tool's `use()` method is called with the LLM-provided arguments. For tools extending `SimpleTool`, this delegates to `__invoke()`, and the return value is automatically wrapped in `Result::success()`. Any exception (except `AgentStopException`) is caught and wrapped in `Result::failure()`.

### 7. Event Emission

The executor dispatches `ToolCallStarted` and `ToolCallCompleted` events around each execution. These events carry timing information and success/failure status, making them useful for logging, metrics, and observability.

### 8. Hook Interception (After)

The `afterToolUse` lifecycle hook runs, allowing inspection or modification of the execution result. Hooks can replace the `ToolExecution` entirely (e.g., to sanitize output or add metadata).

### 9. Result Formatting

Tool execution results are formatted as messages and appended to the conversation. The LLM sees these results on its next turn.

### 10. Loop Continuation

The cycle repeats until the LLM responds without requesting any tool calls, at which point the agent produces its final response.

<a name="the-toolexecution-object"></a>
## The ToolExecution Object

Each tool invocation produces a `ToolExecution` value object that captures the complete execution record:

```php
$execution->id();            // ToolExecutionId -- unique identifier
$execution->toolCall();      // ToolCall -- the original call from the LLM
$execution->name();          // string -- tool name shortcut
$execution->args();          // array -- arguments shortcut
$execution->result();        // Result -- success or failure
$execution->value();         // mixed -- unwrapped success value, or null
$execution->error();         // ?Throwable -- exception on failure, or null
$execution->errorMessage();  // string -- error message, or empty string
$execution->hasError();      // bool -- true if execution failed
$execution->wasBlocked();    // bool -- true if blocked by a hook
$execution->startedAt();     // DateTimeImmutable
$execution->completedAt();   // DateTimeImmutable
$execution->toArray();       // array -- serializable representation
```

The `ToolExecutions` collection aggregates multiple executions from a single step and provides batch-level queries:

```php
$executions->all();           // ToolExecution[]
$executions->first();         // ?ToolExecution
$executions->hasExecutions(); // bool
$executions->hasErrors();     // bool
$executions->havingErrors();  // ToolExecution[] -- only failed ones
$executions->errors();        // ErrorList
$executions->toolCalls();     // ToolCalls -- extract original calls
```

<a name="error-handling"></a>
## Error Handling

Tool failures are handled gracefully by default. If a tool throws an exception, the framework catches it, wraps it in a `Result::failure()` with a `ToolExecutionException`, and reports the error back to the LLM as a tool result. This lets the LLM retry with different arguments or fall back to an alternative approach.

The `AgentStopException` is the one exception that is never caught. Throwing it from within a tool immediately stops the agent loop with the provided `StopSignal`. This is the canonical way for a tool to halt execution programmatically.

### Strict Failure Mode

You can change the default behavior with the `throwOnToolFailure` option on the `ToolExecutor`. When enabled, tool exceptions propagate and halt the agent loop instead of being fed back to the LLM:

```php
$executor = new ToolExecutor(
    tools: $tools,
    events: $events,
    interceptor: $interceptor,
    throwOnToolFailure: true,
);
```

### Stopping on Blocked Tools

The `stopOnToolBlock` option causes the executor to stop processing remaining tool calls in a batch when a hook blocks the first one:

```php
$executor = new ToolExecutor(
    tools: $tools,
    events: $events,
    interceptor: $interceptor,
    stopOnToolBlock: true,
);
```

<a name="the-tool-registry"></a>
## The Tool Registry

For scenarios where tools are numerous or expensive to instantiate, the `ToolRegistry` provides a mutable, lazy-loading container that implements `CanManageTools`:

```php
use Cognesy\Agents\Tool\ToolRegistry;

$registry = new ToolRegistry();

// Register a tool instance directly
$registry->register($myTool);

// Register a factory for lazy instantiation
$registry->registerFactory('expensive_tool', function () {
    return new ExpensiveTool(/* ... */);
});

// The tool is only instantiated when first requested
$tool = $registry->get('expensive_tool');
```

The `ToolRegistry` is used internally by the `ToolsTool` capability, which exposes a meta-tool that lets the LLM browse, search, and inspect available tools at runtime.

<a name="mock-tool-for-testing"></a>
## FakeTool for Testing

When testing agent behavior, use `FakeTool` to create tools with predetermined responses. This avoids external dependencies and makes tests deterministic.

### Static Responses

The simplest form returns the same value regardless of arguments:

```php
use Cognesy\Agents\Tool\Tools\FakeTool;

$tool = FakeTool::returning('search', 'Search the web', 'result text');
```

### Dynamic Responses

Pass a callable handler for responses that depend on the arguments:

```php
$tool = new FakeTool(
    name: 'search',
    description: 'Search the web',
    handler: fn(string $query) => "Results for: {$query}",
);
```

### Full Customization

`FakeTool` also accepts optional `schema`, `metadata`, and `fullSpec` arrays for complete control over how the fake tool presents itself:

```php
$tool = new FakeTool(
    name: 'search',
    description: 'Search the web',
    handler: fn(string $query) => "Results for: {$query}",
    schema: [
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'description' => 'Search the web',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query'],
                ],
                'required' => ['query'],
            ],
        ],
    ],
    metadata: [
        'namespace' => 'web',
        'tags' => ['search'],
    ],
    fullSpec: [
        'parameters' => [
            'query' => 'The search query string',
        ],
        'returns' => 'Search results as a string',
    ],
);
```

When no custom schema is provided, `FakeTool` generates a minimal schema with an empty `properties` object, which is sufficient for most testing scenarios.

<a name="next-steps"></a>
## Next Steps

- [Building Tools](06-building-tools.md) -- practical guide to creating tools with `FunctionTool` and `BaseTool`
- [Building Tools: Advanced Patterns](17-building-tools-advanced.md) -- `ContextAwareTool`, `SimpleTool`, descriptors, and schema strategies
- [Hooks](08-hooks.md) -- intercepting tool calls with `beforeToolUse` and `afterToolUse` hooks
