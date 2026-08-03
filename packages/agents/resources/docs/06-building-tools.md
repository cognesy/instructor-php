---
title: 'Building Tools'
description: 'Step-by-step guide to building tools with FunctionTool, BaseTool, and FakeTool'
---

# Building Tools

This page walks through the two recommended paths for creating tools in the Agents package. Most projects only need one of these:

- **`FunctionTool::fromCallable()`** -- wrap any callable and get typed parameters with auto-generated schema.
- **`BaseTool`** -- extend a base class for tools that need access to agent state or custom behavior.

For lower-level patterns like `ContextAwareTool`, `SimpleTool`, and custom descriptors, see [Building Tools: Advanced Patterns](17-building-tools-advanced.md).

<a name="function-tool"></a>
## FunctionTool (Recommended)

`FunctionTool` is the fastest path to a working tool. It uses PHP reflection to extract the tool name from the function name, the description from the `#[Description]` attribute, and the parameter schema from typed arguments. There is nothing to configure manually.

### Basic Usage

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

The generated tool will have the name `get_weather`, the description from the function-level `#[Description]` attribute, and a JSON schema with a required `city` string parameter documented with its own description.

### Closures and Anonymous Functions

Closures work too, though the generated tool name will be less meaningful. You can use `#[Description]` on both the closure and its parameters:

```php
$tool = FunctionTool::fromCallable(
    #[Description('Search the web for a query')]
    function (
        #[Description('Search query string')] string $query,
        #[Description('Maximum number of results')] int $limit = 10,
    ): string {
        // perform search...
        return "Results for: {$query}";
    }
);
```

### Multiple Parameters and Types

`FunctionTool` supports all common PHP types. Optional parameters (those with default values) are not marked as required in the generated schema:

```php
#[Description('Create a calendar event')]
function create_event(
    #[Description('Event title')] string $title,
    #[Description('Start date in YYYY-MM-DD format')] string $date,
    #[Description('Duration in minutes')] int $duration = 60,
    #[Description('Whether to send reminders')] bool $remind = true,
): string {
    return "Created: {$title} on {$date}";
}

$tool = FunctionTool::fromCallable(create_event(...));
```

### Using Static or Instance Methods

Any callable works -- static methods, instance methods, and invokable objects:

```php
use Cognesy\Schema\Attributes\Description;

class WeatherService {
    #[Description('Get weather forecast for a city')]
    public function forecast(
        #[Description('City name')] string $city,
        #[Description('Number of days')] int $days = 3,
    ): string {
        return "Forecast for {$city}, next {$days} days: sunny";
    }
}

$service = new WeatherService();
$tool = FunctionTool::fromCallable($service->forecast(...));
```

### How Schema Generation Works

When you call `FunctionTool::fromCallable()`, the factory:

1. Uses `CallableSchemaFactory` to extract the function name, description, and parameter types via reflection.
2. Converts the schema to a JSON Schema array via `SchemaFactory`.
3. Caches the JSON schema on the `FunctionTool` instance so reflection only happens once.
4. Wraps the callable in a `Closure` for consistent invocation.

The generated schema follows the OpenAI function-calling format and includes parameter types, descriptions, and required/optional status derived from PHP defaults.

### Accessing the Underlying Callable

If you need to retrieve the original callable (for example, for testing), use the `function()` method:

```php
$callback = $tool->function(); // Returns the Closure
$result = $callback('Paris');
```

<a name="base-tool"></a>
## BaseTool (State-Aware Class Tool)

Use `BaseTool` when you need a class-based tool that can access the current `AgentState` during execution. This is the right choice when your tool needs to read conversation history, check execution metadata, or interact with other parts of the agent's runtime context.

### Basic Usage

Every `BaseTool` subclass must implement `__invoke(mixed ...$args)`. Because `SimpleTool` (the root of the hierarchy) declares `__invoke` with a variadic `mixed` signature, all subclasses must keep this exact signature. Use `$this->arg()` to extract named or positional parameters from the args array.

```php
use Cognesy\Agents\Tool\Tools\BaseTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class WeatherTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'weather',
            description: 'Get the current weather for a city',
        );
    }

    public function __invoke(mixed ...$args): string
    {
        $city = (string) $this->arg($args, 'city', 0, '');
        return "Weather in {$city}: 72F, sunny";
    }

    public function toToolSchema(): ToolDefinition
    {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('city', 'City name'),
                ])
                ->withRequiredProperties(['city'])
        )->toArray());
    }
}
```

### Why Override `toToolSchema()`?

`BaseTool` includes reflective schema support via the `HasReflectiveSchema` trait, which can auto-generate a schema from the `__invoke` method signature. However, because `__invoke` must use the `mixed ...$args` signature, the auto-generated schema will describe a single variadic `mixed` parameter -- not useful for production prompts. You should almost always override `toToolSchema()` to declare the parameters the LLM should provide.

### Defining Parameters with JsonSchema

The `JsonSchema` class provides a fluent API for building parameter schemas without writing raw arrays. It supports all JSON Schema types:

```php
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class MyTool extends BaseTool {
    public function toToolSchema(): ToolDefinition
{
    return ToolDefinition::fromArray(ToolSchema::make(
        name: $this->name(),
        description: $this->description(),
        parameters: JsonSchema::object('parameters')
            ->withProperties([
                JsonSchema::string('query', 'Search query'),
                JsonSchema::integer('limit', 'Max results to return'),
                JsonSchema::boolean('verbose', 'Include detailed output'),
                JsonSchema::enum('format', ['json', 'text', 'csv'], 'Output format'),
                JsonSchema::array('tags')
                    ->withItemSchema(JsonSchema::string()),
            ])
            ->withRequiredProperties(['query'])
    )->toArray());
}
}
```

Available `JsonSchema` factory methods include: `string()`, `integer()`, `number()`, `boolean()`, `enum()`, `array()`, `object()`, and `any()`. Each accepts a name, description, and optional configuration like nullability.

### Extracting Arguments with `$this->arg()`

The `arg()` helper resolves arguments by trying three sources in order: named key, positional index, then default value. This means your tool works correctly whether the LLM passes arguments by name (the typical case) or by position in tests:

```php
public function __invoke(mixed ...$args): string
{
    $query = (string) $this->arg($args, 'query', 0, '');
    $limit = (int) $this->arg($args, 'limit', 1, 10);
    $verbose = (bool) $this->arg($args, 'verbose', 2, false);

    // ... perform search
}
```

The lookup order is: `$args['query']` first, then `$args[0]`, then the default `''`.

### Accessing Agent State

`BaseTool` extends `StateAwareTool`, so the current `AgentState` is available as `$this->agentState` during execution. The framework injects the state automatically before each invocation -- you do not need to set it yourself:

```php
public function __invoke(mixed ...$args): string
{
    // Access conversation step count, execution metadata, etc.
    $stepCount = $this->agentState?->stepCount() ?? 0;

    return "Processed after {$stepCount} steps in the conversation.";
}
```

State is read-only from the tool's perspective. The framework clones the tool and injects the state before each call, so tools are safe to use across multiple invocations without shared mutable state.

### Constructor Defaults

The `BaseTool` constructor accepts optional `name` and `description` parameters. If `name` is omitted, it defaults to the fully qualified class name. If `description` is omitted, it defaults to an empty string:

```php
// Explicit naming (recommended for clear LLM prompts)
parent::__construct(
    name: 'file.read',
    description: 'Read a file from disk',
);

// Class-name fallback (less readable in LLM prompts)
parent::__construct();
```

### Custom Metadata and Instructions

`BaseTool` provides default implementations of `metadata()` and `instructions()` that derive values from the tool name and description. Override them when your tool needs richer documentation for tool registries or browsing:

```php
class MyTool extends BaseTool {
    public function metadata(): array
    {
        return [
            'name' => $this->name(),
            'summary' => 'Search across indexed documents',
            'namespace' => 'search',
            'tags' => ['retrieval', 'rag'],
        ];
    }

    public function instructions(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => [
                'query' => 'The search query. Supports boolean operators.',
                'limit' => 'Maximum number of results. Default: 10.',
            ],
            'returns' => 'JSON string with search results',
            'notes' => ['Results are sorted by relevance score'],
        ];
    }
}
```

The default `metadata()` implementation supports automatic namespace extraction from dotted tool names (e.g., `file.read` extracts namespace `file`) and automatic summary extraction from the first sentence of the description. The `instructions()` method returns the full specification including the reflective parameter schema. This two-level design supports the `ToolsTool` registry pattern where agents can discover tools without loading their complete documentation.

<a name="php-constraint"></a>
## The `__invoke` Signature Constraint

A common question is why `BaseTool` subclasses cannot declare typed parameters on `__invoke`. The answer is a PHP language constraint: `SimpleTool` (the abstract root of the hierarchy) declares `abstract public function __invoke(mixed ...$args): mixed`, and PHP does not allow child classes to narrow the parameter types of an inherited method signature.

This means you cannot write:

```php
// This will NOT work -- PHP fatal error
public function __invoke(string $city): string { ... }
```

Instead, use `$this->arg()` to extract named or positional parameters:

```php
public function __invoke(mixed ...$args): string
{
    $city = (string) $this->arg($args, 'city', 0, '');
    return "Weather in {$city}: 72F, sunny";
}
```

If you want typed parameters with compile-time safety and auto-generated schema, use `FunctionTool::fromCallable()` instead.

<a name="test-helpers"></a>
## Testing Your Tools

### FakeTool for Loop Testing

When writing tests for agent behavior, use `FakeTool` to create tools with predetermined responses. This lets you test the agent loop without real tool implementations:

```php
use Cognesy\Agents\Tool\Tools\FakeTool;

// Simple static return value
$tool = FakeTool::returning('search', 'Search the web', 'result text');

// Dynamic handler for input-dependent responses
$tool = new FakeTool(
    name: 'calculator',
    description: 'Evaluate math expressions',
    handler: fn(string $expression) => (string) eval("return {$expression};"),
);
```

### Testing FunctionTool Directly

You can invoke a `FunctionTool` directly without the agent loop:

```php
$tool = FunctionTool::fromCallable(get_weather(...));

// Via the function() accessor
$result = ($tool->function())('Paris');
assert($result === 'Weather in Paris: 72F, sunny');

// Via the use() method (returns a Result object)
$result = $tool->use(city: 'Paris');
assert($result->isSuccess());
assert($result->unwrap() === 'Weather in Paris: 72F, sunny');
```

### Testing BaseTool Subclasses

Instantiate the tool and call it directly. If the tool reads `$this->agentState`, inject a state first:

```php
$tool = new WeatherTool();

// Without state (agentState will be null)
$result = $tool('Paris');

// With state injected
$state = AgentState::empty()->withUserMessage('test');
$tool = $tool->withAgentState($state);
$result = $tool('Paris');
```

<a name="choosing-a-base-class"></a>
## Which Approach Should I Use?

| Approach | Use when | Schema strategy | State access |
|---|---|---|---|
| `FunctionTool` | You have a callable with typed parameters | Auto-generated from reflection | No |
| `BaseTool` | You need agent state access or class-based organization | Override `toToolSchema()` manually | Yes (`$this->agentState`) |
| `ContextAwareTool` | You need raw `ToolCall` access for tracing | Override `toToolSchema()` manually | Yes (both) |
| `SimpleTool` | You want full low-level control over everything | Override `toToolSchema()` manually | No |

For the vast majority of use cases, `FunctionTool` is the right choice. Reach for `BaseTool` when you need `AgentState` access, and `ContextAwareTool` only when you also need the raw `ToolCall` for correlation or tracing.

<a name="next-steps"></a>
## Next Steps

- [Tools](05-tools.md) -- full reference for the tool system, contracts, and execution lifecycle
- [Building Tools: Advanced Patterns](17-building-tools-advanced.md) -- `ContextAwareTool`, `SimpleTool`, custom descriptors, and schema strategies
