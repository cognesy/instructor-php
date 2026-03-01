---
title: 'Building Tools: Advanced Patterns'
description: 'Lower-level tool patterns: context-aware tools, descriptors, and schema strategy'
---

# Building Tools: Advanced Patterns

Most projects only need [Building Tools](06-building-tools.md).
Use this page when you are writing custom capabilities and need lower-level control.

## Class Hierarchy

| Class | Adds | Typical use |
|---|---|---|
| `SimpleTool` | Descriptor + result wrapper + `$this->arg()` | Full manual control |
| `ReflectiveSchemaTool` | Reflective `toToolSchema()` from `__invoke()` | Auto schema from signatures |
| `FunctionTool` | Wraps callable + cached reflective schema | Typed callable tools |
| `StateAwareTool` | `withAgentState()` / `$this->agentState` | Read current execution state |
| `BaseTool` | `StateAwareTool` + reflective schema + default metadata/instructions | State-aware class tools |
| `ContextAwareTool` | `StateAwareTool` + `withToolCall()` / `$this->toolCall` | Need raw tool call context |

## ContextAwareTool: Access ToolCall and State

Use `ContextAwareTool` when you need call metadata (for tracing/correlation) in addition to state.

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
            description: 'Record tool call metadata and input.',
        ));
    }

    public function __invoke(mixed ...$args): string
    {
        $input = (string) $this->arg($args, 'input', 0, '');
        $callId = (string) ($this->toolCall?->id() ?? '');
        $callId = $callId !== '' ? $callId : 'unknown';

        return "call_id={$callId}; input={$input}";
    }

    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('input', 'Input text to audit'),
                ])
                ->withRequiredProperties(['input'])
        )->toArray();
    }
}
```

## SimpleTool: Full Control

Use `SimpleTool` when you want to manage everything yourself (descriptor, schema, behavior).

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
            description: 'Echo back input text.',
        ));
    }

    public function __invoke(mixed ...$args): string
    {
        return (string) $this->arg($args, 'text', 0, '');
    }

    public function toToolSchema(): array
    {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('text', 'Text to echo'),
                ])
                ->withRequiredProperties(['text'])
        )->toArray();
    }
}
```

## Descriptors as Separate Classes

When tool docs get large, move them into a dedicated descriptor class.
This keeps runtime logic short and reuses documentation cleanly.

```php
use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class BashLikeDescriptor extends ToolDescriptor
{
    public function __construct()
    {
        parent::__construct(
            name: 'my_tool',
            description: 'Run a controlled operation.',
            metadata: [
                'namespace' => 'system',
                'tags' => ['ops'],
            ],
            instructions: [
                'parameters' => [
                    'input' => 'Operation input.',
                ],
                'returns' => 'Operation output string.',
            ],
        );
    }
}
```

## Schema Strategy Matrix

| Class | Schema source | What to do |
|---|---|---|
| `FunctionTool` | Callable reflection (`fromCallable`) | Usually no override |
| `BaseTool` | Reflection of `__invoke(mixed ...$args)` | Usually override `toToolSchema()` for explicit params |
| `ContextAwareTool` | None by default | Implement `toToolSchema()` |
| `StateAwareTool` | None by default | Implement `toToolSchema()` |
| `SimpleTool` | None by default | Implement `toToolSchema()` |

`BaseTool` includes reflective schema support, but because `__invoke` must keep `mixed ...$args`, the generated schema is often too generic for production prompts.

## Parameter Extraction with `$this->arg()`

Use `$this->arg()` to support named and positional arguments in one line:

```php
$path = (string) $this->arg($args, 'path', 0, '');
```

Lookup order is: named key, positional index, then default.

## Related

- [Building Tools](06-building-tools.md)
- [Tools](05-tools.md)
