---
title: 'Building Tools: Advanced Patterns'
description: 'Lower-level tool patterns: context-aware tools, descriptors, registries, and deferred tools'
---

# Building Tools: Advanced Patterns

Most projects only need [Building Tools](06-building-tools.md) with `FunctionTool` or `BaseTool`. This page covers advanced patterns for when you need lower-level control: context-aware tools, raw `SimpleTool` subclasses, custom descriptors, the `ToolRegistry`, and deferred tool providers.

<a name="class-hierarchy"></a>
## Class Hierarchy

The tool class hierarchy is designed so each layer adds exactly one concern. You extend only the level you need:

```
SimpleTool (abstract)
  Descriptor + result wrapper + $this->arg()
  |
  +-- ReflectiveSchemaTool (abstract)
  |     Adds auto-generated toToolSchema() via __invoke reflection
  |     |
  |     +-- FunctionTool (concrete)
  |           Wraps a callable with cached reflective schema
  |
  +-- StateAwareTool (abstract)
        Adds withAgentState() / $this->agentState
        |
        +-- BaseTool (abstract)
        |     Adds reflective schema + default metadata/instructions
        |
        +-- ContextAwareTool (abstract)
              Adds withToolCall() / $this->toolCall
```

| Class | What it adds | When to use |
|---|---|---|
| `SimpleTool` | Descriptor + result wrapper + `$this->arg()` | Full manual control, no state or schema magic |
| `ReflectiveSchemaTool` | Auto-generates `toToolSchema()` from `__invoke()` | Rarely used directly; base for `FunctionTool` |
| `FunctionTool` | Wraps a callable with cached reflective schema | Typed callable tools (most common) |
| `StateAwareTool` | `withAgentState()` / `$this->agentState` | Read current execution state without schema support |
| `BaseTool` | State + reflective schema + metadata/instructions defaults | State-aware class tools (most common class-based approach) |
| `ContextAwareTool` | State + `withToolCall()` / `$this->toolCall` | Tools that need the raw `ToolCall` for correlation or tracing |

### Traits Under the Hood

Each layer in the hierarchy is composed from focused traits. Understanding these traits helps when you need to implement `ToolInterface` directly rather than extending one of the base classes:

| Trait | Provides | Used by |
|---|---|---|
| `HasDescriptor` | Delegates `name()`, `description()`, `metadata()`, `instructions()` to a `CanDescribeTool` instance | `SimpleTool` |
| `HasResultWrapper` | Implements `use()` by calling `__invoke()` in a try/catch, wrapping results in `Result::success()` or `Result::failure()` | `SimpleTool` |
| `HasArgs` | Provides `$this->arg($args, $name, $position, $default)` for named/positional parameter extraction | `SimpleTool` |
| `HasAgentState` | Provides `$this->agentState` and `withAgentState()` (immutable clone + inject) | `StateAwareTool` |
| `HasToolCall` | Provides `$this->toolCall` and `withToolCall()` (immutable clone + inject) | `ContextAwareTool` |
| `HasReflectiveSchema` | Provides `toToolSchema()` and `paramsJsonSchema()` via `CallableSchemaFactory` reflection on `__invoke` | `ReflectiveSchemaTool`, `BaseTool` |

<a name="context-aware-tool"></a>
## ContextAwareTool

`ContextAwareTool` extends `StateAwareTool` and adds access to the raw `ToolCall` object via `$this->toolCall`. This gives your tool the call ID, the tool name as the LLM specified it, and the raw arguments. It is particularly useful for tools that need to correlate their output with specific invocations -- for example, auditing tools, subagent spawners, or tools that emit events with tracing metadata.

The framework injects both the `AgentState` and the `ToolCall` before each invocation via immutable cloning. You do not need to manage this yourself.

```php
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\ContextAwareTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

final class AuditingTool extends ContextAwareTool
{
    public function __construct()
    {
        parent::__construct(new ToolDescriptor(
            name: 'audit_input',
            description: 'Record tool call metadata and input for audit trail.',
        ));
    }

    public function __invoke(mixed ...$args): string
    {
        $input = (string) $this->arg($args, 'input', 0, '');

        // Access the raw ToolCall for correlation
        $callId = (string) ($this->toolCall?->id() ?? 'unknown');

        // Access agent state for context
        $stepCount = $this->agentState?->stepCount() ?? 0;

        return "call_id={$callId}; steps={$stepCount}; input={$input}";
    }

    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('input', 'Input text to audit'),
                ])
                ->withRequiredProperties(['input'])
        )->toArray());
    }
}
```

### Key Differences from BaseTool

There are two important differences to keep in mind when choosing `ContextAwareTool` over `BaseTool`:

1. **No reflective schema.** `ContextAwareTool` does not include the `HasReflectiveSchema` trait, so you must always implement `toToolSchema()` yourself.

2. **Constructor signature.** The constructor takes a `CanDescribeTool` instance (typically a `ToolDescriptor`) rather than plain `name` and `description` strings. This gives you full control over metadata and instructions from the start.

### When to Use ContextAwareTool

Use `ContextAwareTool` when your tool needs any of the following:

- The `ToolCall` ID for log correlation or distributed tracing.
- The raw arguments as the LLM specified them, before any processing.
- The tool name as it appears in the LLM's request (which may differ from the registered name in edge cases).
- Both state and tool call context in the same tool.

If you only need agent state, prefer `BaseTool`. If you need neither state nor tool call context, prefer `FunctionTool` or `SimpleTool`.

<a name="simple-tool"></a>
## SimpleTool

`SimpleTool` is the root abstract class in the tool hierarchy. It provides only the essentials: a descriptor for identity, a result wrapper that catches exceptions and returns `Result` objects, and the `$this->arg()` helper. Everything else -- schema, state access, tool call access -- is your responsibility.

Use `SimpleTool` when you want complete control over a tool's behavior and do not need agent state or reflective schema generation.

```php
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

final class EchoTool extends SimpleTool
{
    public function __construct()
    {
        parent::__construct(new ToolDescriptor(
            name: 'echo_text',
            description: 'Echo back the provided text unchanged.',
        ));
    }

    public function __invoke(mixed ...$args): string
    {
        return (string) $this->arg($args, 'text', 0, '');
    }

    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('text', 'Text to echo back'),
                ])
                ->withRequiredProperties(['text'])
        )->toArray());
    }
}
```

### The Result Wrapper

`SimpleTool` (via the `HasResultWrapper` trait) implements `ToolInterface::use()` by calling your `__invoke()` method inside a try/catch block. The behavior is straightforward:

- If `__invoke()` returns normally, the value is wrapped in `Result::success()`.
- If `__invoke()` throws any exception, the exception is wrapped in `Result::failure()` and the error message is sent back to the LLM.
- The one exception that is **never caught** is `AgentStopException`. Throwing this from within a tool immediately halts the agent loop with the provided `StopSignal`.

This means you can write `__invoke()` as a normal method that throws on error, and the framework will handle it gracefully:

```php
public function __invoke(mixed ...$args): string
{
    $path = (string) $this->arg($args, 'path', 0, '');
    if (!file_exists($path)) {
        throw new \RuntimeException("File not found: {$path}");
    }
    return file_get_contents($path);
}
```

The LLM receives the error message and can decide whether to retry with different arguments or take a different approach entirely.

### Stopping the Agent Loop From a Tool

If your tool detects a condition that should stop the entire agent, throw an `AgentStopException` with a `StopSignal`:

```php
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Continuation\StopReason;

public function __invoke(mixed ...$args): string
{
    $input = (string) $this->arg($args, 'input', 0, '');
    if ($input === 'ABORT') {
        throw new AgentStopException(
            signal: new StopSignal(
                reason: StopReason::StopRequested,
                message: 'Abort signal received',
            ),
        );
    }
    return "Processed: {$input}";
}
```

<a name="state-aware-tool"></a>
## StateAwareTool

`StateAwareTool` sits between `SimpleTool` and `BaseTool` in the hierarchy. It adds `CanAccessAgentState` support (via the `HasAgentState` trait) but does not include reflective schema generation or default metadata/instructions.

Use `StateAwareTool` directly when you need agent state access but want full manual control over everything else. In practice, most developers use `BaseTool` instead, which adds schema and metadata defaults on top of `StateAwareTool`.

```php
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\StateAwareTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

final class StepCounterTool extends StateAwareTool
{
    public function __construct()
    {
        parent::__construct(new ToolDescriptor(
            name: 'step_counter',
            description: 'Return the current step count.',
        ));
    }

    public function __invoke(mixed ...$args): string
    {
        return (string) ($this->agentState?->stepCount() ?? 0);
    }

    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
        )->toArray());
    }
}
```

<a name="reflective-schema-tool"></a>
## ReflectiveSchemaTool

`ReflectiveSchemaTool` extends `SimpleTool` and adds automatic `toToolSchema()` generation from the `__invoke()` method signature via the `HasReflectiveSchema` trait. It is the base class for `FunctionTool` and is rarely extended directly.

The reflective schema uses `CallableSchemaFactory` to introspect the `__invoke` method at runtime and generates a JSON Schema from the parameter types and `#[Description]` attributes. The result is cached after the first call to `paramsJsonSchema()`.

If you are building a class-based tool and want reflective schema without state access, extend `ReflectiveSchemaTool`. However, because `__invoke` must use the `mixed ...$args` signature, the generated schema will not be useful for production -- making this class primarily an internal building block.

<a name="descriptors"></a>
## Descriptors as Separate Classes

When a tool's documentation is extensive -- detailed usage instructions, parameter descriptions, error codes, examples -- it can overwhelm the tool's runtime logic. In these cases, extract the documentation into a dedicated descriptor class that extends `ToolDescriptor`.

### The ToolDescriptor Class

`ToolDescriptor` is a readonly value object that implements `CanDescribeTool`. Its constructor accepts four arguments:

```php
use Cognesy\Agents\Tool\ToolDescriptor;

$descriptor = new ToolDescriptor(
    name: 'search',
    description: 'Full-text search across documents.',
    metadata: [                    // Merged with defaults (name, summary)
        'namespace' => 'retrieval',
        'tags' => ['search', 'rag'],
    ],
    instructions: [                // Merged with defaults (name, description, parameters, returns)
        'parameters' => [
            'query' => 'Natural language search query.',
            'limit' => 'Maximum results (1-100, default 10).',
        ],
        'returns' => 'JSON array of matching documents.',
        'errors' => [
            'empty_query' => 'Returned when query is blank.',
        ],
    ],
);
```

The `metadata` and `instructions` arrays are merged with default values at read time:

- **`metadata()`** merges with `['name' => ..., 'summary' => ...]`
- **`instructions()`** merges with `['name' => ..., 'description' => ..., 'parameters' => [], 'returns' => 'mixed']`

This means you only need to specify the additional fields your tool requires.

### Subclassing ToolDescriptor

For tools with extensive documentation, create a dedicated descriptor subclass:

```php
use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class SearchToolDescriptor extends ToolDescriptor
{
    public function __construct()
    {
        parent::__construct(
            name: 'search',
            description: 'Search indexed documents by query.',
            metadata: [
                'namespace' => 'retrieval',
                'tags' => ['search', 'rag'],
            ],
            instructions: [
                'parameters' => [
                    'query' => 'Natural language search query.',
                    'limit' => 'Maximum results (1-100, default 10).',
                    'filters' => 'Optional key-value filters.',
                ],
                'returns' => 'JSON array of matching documents with relevance scores.',
                'errors' => [
                    'empty_query' => 'Returned when query is blank.',
                    'index_unavailable' => 'Returned when the search index is offline.',
                ],
                'notes' => [
                    'Results are sorted by relevance score descending.',
                    'Use filters to narrow by date, category, or author.',
                ],
            ],
        );
    }
}
```

Then pass the descriptor to your tool's constructor:

```php
final class SearchTool extends SimpleTool
{
    public function __construct()
    {
        parent::__construct(new SearchToolDescriptor());
    }

    // ... __invoke() and toToolSchema()
}
```

This pattern keeps tool runtime logic clean and makes documentation reusable across tools that share the same descriptor structure.

### How Metadata and Instructions Differ

The two documentation levels serve different audiences:

**`metadata()`** returns lightweight information suitable for listing or browsing: name, summary, namespace, and tags. It is designed for the "list" action of a tool registry where an agent needs to scan many tools quickly without consuming context.

**`instructions()`** returns the full specification: name, description, parameters, return type, errors, examples, and notes. It is designed for the "help" action where an agent needs the complete documentation for a specific tool before using it.

`BaseTool` provides default implementations that extract a summary from the description (first sentence or first line, truncated to 80 characters) and a namespace from dotted tool names (e.g., `file.read` yields namespace `file`).

<a name="tool-registry"></a>
## ToolRegistry

The `ToolRegistry` is a mutable container that implements `CanManageTools`. Unlike the immutable `Tools` collection (which is a value object for passing tools around), `ToolRegistry` supports lazy instantiation through factories and is designed for managing large numbers of tools at runtime.

### Registering Tools

```php
use Cognesy\Agents\Tool\ToolRegistry;

$registry = new ToolRegistry();

// Register a tool instance directly
$registry->register($searchTool);

// Register a factory for lazy instantiation
$registry->registerFactory('heavy_tool', function () {
    return new HeavyTool(); // Only created when first needed
});
```

### Querying the Registry

```php
$registry->has('search');         // true
$registry->get('search');         // ToolInterface (resolves factory on first call)
$registry->names();               // ['search', 'heavy_tool']
$registry->count();               // 2
$registry->all();                 // Resolves all factories, returns keyed array
```

When you call `get()` on a factory-registered tool, the factory is invoked once and the resulting instance is cached for subsequent calls. This makes `ToolRegistry` suitable for tools that are expensive to construct or that depend on runtime context.

If a tool is not found, `get()` throws an `InvalidToolException`.

### ToolsTool: Agent-Facing Tool Discovery

The `ToolsTool` is a built-in tool that exposes the `ToolRegistry` to the LLM, letting agents discover and browse available tools at runtime. It supports three actions:

| Action | Parameters | Description |
|---|---|---|
| `list` | `limit` (optional) | Returns `metadata()` for all registered tools |
| `help` | `tool` (required) | Returns full `instructions()` for a specific tool by name |
| `search` | `query` (required), `limit` (optional) | Searches tool names, descriptions, summaries, namespaces, and tags by keyword |

This pattern is useful when an agent has access to many tools but should not receive all their schemas upfront (which would consume context window space). Instead, the agent uses `ToolsTool` to discover relevant tools, then calls them by name.

```php
use Cognesy\Agents\Capability\Tools\ToolsTool;
use Cognesy\Agents\Tool\ToolRegistry;

$registry = new ToolRegistry();
$registry->register($searchTool);
$registry->register($fileTool);

$toolsTool = new ToolsTool($registry);
// Now add $toolsTool to the agent's Tools collection
```

<a name="deferred-tools"></a>
## Deferred Tool Providers

Some tools cannot be constructed until the agent loop is being assembled, because they depend on the tool-use driver, the event dispatcher, or the current set of already-registered tools. Deferred tool providers solve this by delaying tool construction until build time.

### The `CanProvideDeferredTools` Interface

Implement this interface to provide tools that are resolved lazily during the `AgentBuilder::build()` process:

```php
use Cognesy\Agents\Builder\Contracts\CanProvideDeferredTools;
use Cognesy\Agents\Builder\Data\DeferredToolContext;
use Cognesy\Agents\Collections\Tools;

final class SubagentToolProvider implements CanProvideDeferredTools
{
    public function provideTools(DeferredToolContext $context): Tools
    {
        // Access build-time dependencies
        $existingTools = $context->tools();
        $driver = $context->toolUseDriver();
        $events = $context->events();

        return new Tools(
            new SubagentTool($driver, $events),
        );
    }
}
```

The `DeferredToolContext` gives providers access to three things:

| Method | Returns | Purpose |
|---|---|---|
| `tools()` | `Tools` | The current tool collection as it exists at resolution time |
| `toolUseDriver()` | `CanUseTools` | The driver for making nested LLM calls (needed by subagent tools) |
| `events()` | `CanHandleEvents` | The event dispatcher for emitting events |

### The `UseToolFactory` Capability

For simple cases where you just need a factory closure rather than a full class, the `UseToolFactory` capability wraps a callable as a deferred provider:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseToolFactory;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Events\Contracts\CanHandleEvents;

$loop = AgentBuilder::base()
    ->withCapability(new UseToolFactory(
        function (Tools $tools, CanUseTools $driver, CanHandleEvents $events) {
            return new SubagentTool($driver, $events);
        }
    ))
    ->build();
```

The factory callable receives the same three arguments that `DeferredToolContext` provides. The returned `ToolInterface` is wrapped in a `Tools` collection and merged into the agent's tool set.

<a name="schema-strategy"></a>
## Schema Strategy Matrix

| Class | Default schema source | Recommendation |
|---|---|---|
| `FunctionTool` | Callable reflection via `fromCallable()` | Usually no override needed |
| `BaseTool` | Reflection of `__invoke(mixed ...$args)` | Override `toToolSchema()` for explicit parameters |
| `ContextAwareTool` | None (no `HasReflectiveSchema`) | Must implement `toToolSchema()` |
| `StateAwareTool` | None (no `HasReflectiveSchema`) | Must implement `toToolSchema()` |
| `SimpleTool` | None (no `HasReflectiveSchema`) | Must implement `toToolSchema()` |
| `ReflectiveSchemaTool` | Reflection of `__invoke()` | Usually no override needed (but see caveat) |

`BaseTool` inherits reflective schema support via the `HasReflectiveSchema` trait, but because `__invoke` must use `mixed ...$args`, the auto-generated schema describes a single variadic parameter. This is rarely useful for production prompts. Always override `toToolSchema()` in `BaseTool` subclasses.

### Building Schema Manually

All manual schemas use the `ToolSchema` and `JsonSchema` helpers:

```php
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class SearchTool extends BaseTool
{
    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('query', 'Search query'),
                    JsonSchema::integer('limit', 'Max results')
                        ->withMeta(['minimum' => 1, 'maximum' => 100]),
                    JsonSchema::enum('format', ['json', 'text'], 'Output format'),
                    JsonSchema::array('tags')
                        ->withItemSchema(JsonSchema::string()),
                    JsonSchema::object('filters')
                        ->withProperties([
                            JsonSchema::string('category', 'Filter by category'),
                            JsonSchema::string('date_from', 'Start date (YYYY-MM-DD)'),
                        ]),
                ])
                ->withRequiredProperties(['query'])
        )->toArray());
    }
}
```

The resulting array follows the OpenAI function-calling format:

```php
[
    'type' => 'function',
    'function' => [
        'name' => 'search',
        'description' => 'Search documents',
        'parameters' => [
            'type' => 'object',
            'properties' => [...],
            'required' => ['query'],
        ],
    ],
]
```

<a name="parameter-extraction"></a>
## Parameter Extraction with `$this->arg()`

The `arg()` method (from the `HasArgs` trait) resolves a parameter from the arguments array using a three-step lookup:

```php
$value = $this->arg($args, $name, $position, $default);
```

1. **Named key** -- checks `$args[$name]` (the typical case when the LLM passes an associative array)
2. **Positional index** -- checks `$args[$position]` (useful for direct invocation in tests)
3. **Default value** -- falls back to `$default`

```php
// Extract 'path' by name, or position 0, or default to empty string
$path = (string) $this->arg($args, 'path', 0, '');

// Extract 'limit' by name, or position 1, or default to 10
$limit = (int) $this->arg($args, 'limit', 1, 10);

// Extract 'verbose' by name, or position 2, or default to false
$verbose = (bool) $this->arg($args, 'verbose', 2, false);
```

Always cast the return value to the expected type, since the LLM may pass values as strings even for numeric parameters.

<a name="implementing-tool-interface"></a>
## Implementing ToolInterface Directly

If none of the base classes fit your needs, you can implement `ToolInterface` directly. You must provide three methods:

```php
use Cognesy\Agents\Tool\Contracts\CanDescribeTool;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Utils\Result\Result;

final class CustomTool implements ToolInterface
{
    private ToolDescriptor $descriptor;

    public function __construct()
    {
        $this->descriptor = new ToolDescriptor(
            name: 'custom',
            description: 'A fully custom tool.',
        );
    }

    public function use(mixed ...$args): Result
    {
        try {
            $value = $this->execute($args);
            return Result::success($value);
        } catch (\Throwable $e) {
            return Result::failure($e);
        }
    }

    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray([
            'type' => 'function',
            'function' => [
                'name' => 'custom',
                'description' => 'A fully custom tool.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string', 'description' => 'Input value'],
                    ],
                    'required' => ['input'],
                ],
            ],
        ]);
    }

    public function descriptor(): CanDescribeTool
    {
        return $this->descriptor;
    }

    private function execute(array $args): string
    {
        return 'Result: ' . ($args['input'] ?? '');
    }
}
```

If your custom tool needs state or tool call injection, also implement `CanAccessAgentState` and/or `CanAccessToolCall`. The framework checks for these interfaces during tool preparation and calls the appropriate `with*()` methods.

<a name="real-world-example"></a>
## Building a Complete Tool: Real-World Example

Here is a condensed view of how a production tool is structured, demonstrating the `SimpleTool` pattern with a separate descriptor, manual schema, and `$this->arg()`:

```php
use Cognesy\Agents\Tool\ToolDescriptor;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

// Step 1: Descriptor in a separate class
final readonly class BashToolDescriptor extends ToolDescriptor
{
    public function __construct()
    {
        parent::__construct(
            name: 'bash',
            description: 'Execute a bash command in a sandboxed environment.',
            metadata: ['namespace' => 'system', 'tags' => ['shell', 'execution']],
            instructions: [
                'parameters' => ['command' => 'The bash command to execute'],
                'returns' => 'Command output (stdout/stderr) with exit code',
            ],
        );
    }
}

// Step 2: Tool class with manual schema and injected dependencies
final class BashTool extends SimpleTool
{
    public function __construct(private CanExecuteCommand $sandbox)
    {
        parent::__construct(new BashToolDescriptor());
    }

    public function __invoke(mixed ...$args): string
    {
        $command = (string) $this->arg($args, 'command', 0, '');
        $result = $this->sandbox->execute(['bash', '-c', $command]);
        return $result->stdout();
    }

    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('command', 'The bash command to execute'),
                ])
                ->withRequiredProperties(['command'])
        )->toArray());
    }
}
```

This structure separates concerns cleanly: the descriptor owns documentation, the tool class owns behavior, and the schema is explicit.

<a name="related"></a>
## Related

- [Tools](05-tools.md) -- overview, registration, contracts, and execution lifecycle
- [Building Tools](06-building-tools.md) -- quick path with `FunctionTool` and `BaseTool`
