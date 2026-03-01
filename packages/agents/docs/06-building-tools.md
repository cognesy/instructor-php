---
title: 'Building Tools'
description: 'Quick path for building tools with FunctionTool or BaseTool'
---

# Building Tools

Most projects only need one of two paths:

- `FunctionTool::fromCallable()` for typed, fast tool creation
- `BaseTool` for state-aware class tools with custom schema

If you need lower-level patterns, see [Building Tools: Advanced Patterns](17-building-tools-advanced.md).

## Quick Path 1: FunctionTool (recommended)

Use this when you want typed parameters and auto-generated schema.

```php
use Cognesy\Agents\Tool\Tools\FunctionTool;
use Cognesy\Schema\Attributes\Description;

$tool = FunctionTool::fromCallable(
    function (
        #[Description('City name')] string $city,
    ): string {
        return "Weather in {$city}: 72F, sunny";
    }
);
```

## Quick Path 2: BaseTool (state-aware class)

Use this when you need access to `AgentState` (`$this->agentState`) or custom behavior in a class.

```php
use Cognesy\Agents\Tool\Tools\BaseTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class WeatherTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'weather',
            description: 'Get weather for a city',
        );
    }

    public function __invoke(mixed ...$args): string
    {
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

## Important PHP Constraint

`SimpleTool` declares `__invoke(mixed ...$args)`, so subclasses (`BaseTool`, `ContextAwareTool`, `SimpleTool`) must keep that signature.

If you need typed parameters, use `FunctionTool::fromCallable()`.

## Test Helpers

Use `MockTool` when testing loop behavior:

```php
use Cognesy\Agents\Tool\Tools\MockTool;

$tool = MockTool::returning('search', 'Search the web', 'result text');
```

## Which Base Class Should I Use?

| Base class | Use when | Schema strategy |
|---|---|---|
| `FunctionTool` | You can provide a callable | Auto from typed callable |
| `BaseTool` | You need state-aware class behavior | Usually define manually |
| `ContextAwareTool` | You need raw `ToolCall` access | Manual |
| `SimpleTool` | You want full low-level control | Manual |

## Next Step

For `ContextAwareTool`, `SimpleTool`, descriptor extraction, and schema strategy details, see:

- [Building Tools: Advanced Patterns](17-building-tools-advanced.md)
