---
title: 'Building Tools'
description: 'Create custom tools by extending BaseTool with auto-generated parameter schemas'
---

# Building Tools

## Class Hierarchy

```
SimpleTool (abstract)               – lowest level; schema is manual
├── ReflectiveSchemaTool (abstract) – adds auto-schema from typed __invoke()
│   └── FunctionTool                – wraps any callable
└── StateAwareTool (abstract)       – adds $this->agentState
    ├── BaseTool (abstract)         – auto-schema + agent state  ← use this
    └── ContextAwareTool (abstract) – mixed args + agent state + $this->toolCall
```

Pick the lowest class that gives you what you need.

---

## BaseTool — recommended starting point

Extend `BaseTool`, implement `__invoke(mixed ...$args)`, and override `toToolSchema()`.
Use `$this->arg()` to extract named or positional parameters. `$this->agentState` gives access to
the current `AgentState` (step count, token usage, message history).

> **PHP constraint:** `__invoke()` must match the `mixed ...$args` signature declared in `SimpleTool`.
> Typed named parameters are not allowed in subclasses — use `FunctionTool::fromCallable()` if you want typed params with auto-generated schema.

```php
use Cognesy\Agents\Tool\Tools\BaseTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class CalculatorTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'calculator',
            description: 'Performs basic arithmetic on two numbers.',
        );
    }

    public function __invoke(mixed ...$args): string
    {
        $a  = (float)  $this->arg($args, 'a', 0, 0);
        $b  = (float)  $this->arg($args, 'b', 1, 0);
        $op = (string) $this->arg($args, 'operation', 2, 'add');

        return (string) match ($op) {
            'add'      => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide'   => $b !== 0.0 ? $a / $b : 'Error: division by zero',
            default    => "Error: unknown operation '{$op}'",
        };
    }

    #[\Override]
    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::number('a', 'First operand'),
                    JsonSchema::number('b', 'Second operand'),
                    JsonSchema::string('operation', 'Operation: add, subtract, multiply, or divide'),
                ])
                ->withRequiredProperties(['a', 'b', 'operation'])
        )->toArray();
    }
}
```

---

## FunctionTool — wrap a callable (typed params, auto-schema)

Wrap any callable without creating a class. The schema is **auto-generated** from the function
signature — this is where typed named parameters and `#[Description]` attributes work:

```php
use Cognesy\Agents\Tool\Tools\FunctionTool;
use Cognesy\Schema\Attributes\Description;

$tool = FunctionTool::fromCallable(
    function (
        #[Description('First operand')] float $a,
        #[Description('Second operand')] float $b,
        #[Description('Operation: add, subtract, multiply, or divide')] string $operation,
    ): string {
        return (string) match ($operation) {
            'add'      => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide'   => $b !== 0.0 ? $a / $b : 'Error: division by zero',
            default    => "Error: unknown operation '{$operation}'",
        };
    }
);
```

The tool name and description are inferred from the callable. For named functions the function name is used;
for closures, wrap in a named function or use a named method reference.

---

## ContextAwareTool — access the raw ToolCall

When you need the raw `ToolCall` object (call ID, unparsed arguments, etc.), extend `ContextAwareTool` instead of `BaseTool`.
Unlike `BaseTool`, it does **not** auto-generate a schema — you must implement `toToolSchema()` manually.
Parameters are received as `mixed ...$args`; use `$this->arg()` to extract them by name or position.

```php
use Cognesy\Agents\Tool\Tools\ContextAwareTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class AuditingTool extends ContextAwareTool
{
    public function __construct()
    {
        parent::__construct(new \Cognesy\Agents\Tool\ToolDescriptor(
            name: 'auditing_tool',
            description: 'Records tool call metadata for auditing.',
        ));
    }

    public function __invoke(mixed ...$args): string
    {
        $input = $this->arg($args, 'input', 0, '');
        $callId = $this->toolCall?->id ?? 'unknown';
        return "Recorded call {$callId} with input: {$input}";
    }

    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('input', 'The input to audit'),
                ])
                ->withRequiredProperties(['input'])
        )->toArray();
    }
}
```

---

## SimpleTool — full control

`SimpleTool` is the lowest-level base. It has no auto-schema and no state injection.
Use it when you want to manage the descriptor and schema yourself — this is what most built-in tools (`BashTool`, `ReadFileTool`, etc.) do.

```php
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Agents\Tool\ToolDescriptor;

class MyLowLevelTool extends SimpleTool
{
    public function __construct()
    {
        parent::__construct(new ToolDescriptor(
            name: 'my_tool',
            description: 'Does something very specific.',
        ));
    }

    public function __invoke(mixed ...$args): string
    {
        $value = $this->arg($args, 'value', 0, '');
        return "Got: {$value}";
    }

    public function toToolSchema(): array
    {
        // manual schema required
        return [ /* ... */ ];
    }
}
```

---

## Extracting parameters — $this->arg()

All tool base classes include the `HasArgs` trait, which provides `$this->arg()` for extracting
parameters by name or positional index with an optional default:

```php
$value = $this->arg($args, 'value', 0, 'default');
//                          ↑name  ↑pos  ↑default
```

This is only needed when using `ContextAwareTool` or `SimpleTool` (which receive `mixed ...$args`).
With `BaseTool`, parameters arrive as typed named arguments directly.

---

## Externalizing descriptors with ToolDescriptor

For tools with rich documentation — examples, usage notes, error descriptions — extract the
descriptor into its own class. This keeps tool logic clean and makes documentation reusable.

```php
use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class MyToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'my_tool',
            description: 'Does something useful with detailed guidance.',
            metadata: [
                'namespace' => 'domain',
                'tags' => ['analysis', 'data'],
            ],
            instructions: [
                'parameters' => [
                    'input' => 'The data to process.',
                ],
                'returns' => 'Processed result as string.',
                'usage' => [
                    'Pass structured data for best results.',
                ],
                'errors' => [
                    'Returns error message on invalid input.',
                ],
            ],
        );
    }
}
```

Wire it into your tool by overriding `descriptor()`:

```php
class MyTool extends BaseTool
{
    private MyToolDescriptor $desc;

    public function __construct() {
        $this->desc = new MyToolDescriptor();
        parent::__construct(
            name: $this->desc->name(),
            description: $this->desc->description(),
        );
    }

    #[\Override]
    public function descriptor(): CanDescribeTool {
        return $this->desc;
    }

    public function __invoke(
        #[Description('The data to process')] string $input,
    ): string {
        return "Processed: {$input}";
    }
}
```

Most built-in tools use this pattern — `BashTool` has `BashToolDescriptor`, each file tool has its own.
`metadata()` and `instructions()` power progressive disclosure: registries show summaries via `metadata()`;
the LLM receives full specifications via `instructions()` when needed.

---

## MockTool for testing

```php
use Cognesy\Agents\Tool\Tools\MockTool;

$tool = MockTool::returning('my_tool', 'Does something', 'fixed result');

// Or with custom logic
$tool = new MockTool('my_tool', 'Does something', fn($x) => strtoupper($x));
```

---

## When to override toToolSchema()

| Base class | Auto-schema | Override needed? |
|---|---|---|
| `BaseTool` | No — `__invoke()` must be `mixed ...$args` | **Yes** |
| `FunctionTool` | Yes — from typed callable signature | No |
| `ContextAwareTool` | No | **Yes** |
| `SimpleTool` | No | **Yes** |
