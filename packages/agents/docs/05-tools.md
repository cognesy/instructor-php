---
title: 'Tools'
description: 'Register and use tools that let agents take actions based on LLM decisions'
---

# Tools

Tools let the agent take actions. The LLM decides which tool to call and with what arguments.

## Registering Tools

Pass tools to the `Tools` collection:

```php
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Tool\Tools\MockTool;

$calculator = MockTool::returning('calculator', 'Performs math', '42');
$tools = new Tools($calculator);
```

## Using FunctionTool

Wrap any callable as a tool. The tool name and parameter schema are auto-generated from the function signature. Use a named function so the tool gets a meaningful name:

```php
use Cognesy\Agents\Tool\Tools\FunctionTool;

function get_weather(string $city): string {
    return "Weather in {$city}: 72F, sunny";
}

$tool = FunctionTool::fromCallable(get_weather(...));

$tools = new Tools($tool);
```

## Multiple Tools

Pass multiple tools to `Tools` and the LLM chooses which to call:

```php
use Cognesy\Agents\Tool\Tools\FunctionTool;
use Cognesy\Agents\Collections\Tools;

function get_weather(string $city): string {
    return "Weather in {$city}: 72F, sunny";
}

function calculate(string $expression): string {
    return match ($expression) {
        '2+2' => '4',
        default => 'unsupported expression',
    };
}

$tools = new Tools(
    FunctionTool::fromCallable(get_weather(...)),
    FunctionTool::fromCallable(calculate(...)),
);
```

## Agent with Tools

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;

$loop = AgentLoop::default()->withTools($tools);

$state = AgentState::empty()->withUserMessage('What is the weather in Paris?');
$result = $loop->execute($state);
// LLM calls the weather tool, gets result, then responds
```

## Tool Contracts

Every tool implements two interfaces:

### ToolInterface

The execution and schema contract:

```php
interface ToolInterface {
    public function use(mixed ...$args): Result;    // execute the tool
    public function toToolSchema(): array;           // JSON schema sent to LLM
    public function descriptor(): CanDescribeTool;   // metadata accessor
}
```

### CanDescribeTool

The description contract — provides identity and documentation:

```php
interface CanDescribeTool {
    public function name(): string;          // tool name (e.g., 'read_file')
    public function description(): string;   // what the tool does
    public function metadata(): array;       // summary for browsing/discovery
    public function instructions(): array;   // full specification with parameters
}
```

`metadata()` returns lightweight info (name, summary, namespace) for tool listings. `instructions()` returns the complete specification including parameters and return type.

### BaseTool

`BaseTool` is the standard state-aware base class for custom tools. Because `SimpleTool`
declares `__invoke(mixed ...$args)`, BaseTool subclasses should keep that signature,
extract parameters via `$this->arg()`, and usually provide an explicit `toToolSchema()`.

```php
use Cognesy\Agents\Tool\Tools\BaseTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class WeatherTool extends BaseTool
{
    public function __construct() {
        parent::__construct(
            name: 'weather',
            description: 'Get current weather for a city',
        );
    }

    public function __invoke(mixed ...$args): string {
        $city = (string) $this->arg($args, 'city', 0, '');
        return "Weather in {$city}: 72F, sunny";
    }

    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('city', 'City name'),
                ])
                ->withRequiredProperties(['city'])
        )->toArray();
    }
}
```

See [Building Tools](06-building-tools.md) for the quick path, then [Building Tools: Advanced Patterns](17-building-tools-advanced.md) for lower-level patterns.

## How It Works

1. LLM sees tool schemas and decides to call a tool
2. `ToolExecutor` runs the tool with provided arguments
3. Tool results are formatted as messages and fed back to the LLM
4. LLM uses the results to formulate a final response
5. Loop continues until LLM responds without tool calls
