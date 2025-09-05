# ToolUse Component

ToolUse orchestrates LLM tool-calling loops: it asks an LLM for tool calls, executes tools, appends follow‑up messages, and repeats until continuation criteria indicate completion.

This document explains how to use ToolUse, customize its behavior, and integrate it into an agent system.

## Overview

- Orchestrator: `Cognesy\Addons\ToolUse\ToolUse`
- Driver: `Drivers\ToolCallingDriver` (talks to `polyglot` Inference)
- State: `ToolUseState` (messages, tools, usage, variables, steps, status)
- Step: `ToolUseStep` (LLM response + tool calls/executions + follow‑up messages + usage)
- Tools: `Tools` registry of `ToolInterface` implementations (`FunctionTool`, or custom tools)
- Processors: post‑step processors (accumulate usage, update step, etc.)
- Continuation criteria: stop/continue logic per step (steps/tokens/time/retries/finish reason)
- Observers: step‑level (`ToolUseObserver`) and tool‑level (`ToolsObserver`) hooks

## Quick Start

```php
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Addons\ToolUse\Drivers\ToolCallingDriver;

function add_numbers(int $a, int $b): int { return $a + $b; }

$driver = new ToolCallingDriver(llm: LLMProvider::new());
$toolUse = (new ToolUse)
    ->withDriver($driver)
    ->withMessages('Add numbers 2 and 3')
    ->withTools([
        FunctionTool::fromCallable(add_numbers(...)),
    ]);

$final = $toolUse->finalStep();
echo $final->response();
```

- `withTools([...])` accepts an array of tool implementations or callables (wrapped via `FunctionTool`).
- `withMessages('...')` seeds the conversation (string | array | `Messages`).
- Call `finalStep()` for a full loop; or iterate with `nextStep()` / `iterator()`.

## Defining Tools

Option A: From functions

```php
use Cognesy\Addons\ToolUse\Tools\FunctionTool;

function search(string $q): array { /* ... */ }
$searchTool = FunctionTool::fromCallable(search(...));
```

Option B: Custom tool class

```php
use Cognesy\Addons\ToolUse\Tools\BaseTool;
use Cognesy\Utils\Result\Result;

final class GetTime extends BaseTool {
    protected string $name = 'get_time';
    protected string $description = 'Returns current timestamp';
    public function __invoke(): int { return time(); }
}
```

Notes:
- Tool schemas are generated via `StructureFactory` from the `__invoke` signature.
- Return values are wrapped in `Result::success(...)` or `Result::failure(...)` at the tool boundary.

## Failure Handling

- Default: Tool failures do not throw. Instead, `Result::failure(...)` is recorded in `ToolExecution` and a follow‑up error message is appended for the LLM.
- To restore legacy exception behavior:

```php
$tools = (new Tools())->withThrowOnToolFailure(true);
$toolUse->withTools($tools);
```

## Continuation Criteria

Default criteria include:
- `StepsLimit($maxSteps)`
- `TokenUsageLimit($maxTokens)`
- `ExecutionTimeLimit($seconds)`
- `RetryLimit($maxRetries)` — based on consecutive failed steps
- `ErrorPresenceCheck()` — stop on any errors
- `ToolCallPresenceCheck()` — continue only if tool calls present
- `FinishReasonCheck($finishReasons)` — stop when finish reason is in set

Customize:

```php
use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;

$toolUse->withDefaultContinuationCriteria(maxSteps: 5);
// or
$toolUse->withContinuationCriteria(new StepsLimit(10));
```

## Step Processors

Processors post‑process each step (accumulate usage, update step list, append follow‑up messages). Defaults:
- `AccumulateTokenUsage`
- `UpdateStep`
- `AppendContextVariables` (current behavior retained)
- `AppendStepMessages`

Add or override:

```php
$toolUse->withProcessors(new MyCustomProcessor());
```

## Observability

Step-level hooks:

```php
use Cognesy\Addons\ToolUse\Contracts\ToolUseObserver;

final class MyObserver implements ToolUseObserver {
    public function onStepStart(ToolUseState $state): void {}
    public function onStepEnd(ToolUseState $state, ToolUseStep $step): void {}
}

$toolUse->withObserver(new MyObserver());
```

Tool-level hooks:

```php
use Cognesy\Addons\ToolUse\Contracts\ToolsObserver;

final class MyToolsObserver implements ToolsObserver {
    public function onToolStart(ToolUseState $state, \Cognesy\Polyglot\Inference\Data\ToolCall $call): void {}
    public function onToolEnd(ToolUseState $state, \Cognesy\Addons\ToolUse\ToolExecution $exec): void {}
}

$state = $toolUse->state();
$state->tools()->withObserver(new MyToolsObserver());
```

## Argument Validation

Before invoking a tool, `Tools` performs a pragmatic check against the tool’s JSON schema and returns a failure if required parameters are missing. The failure is surfaced to the LLM through a tool error message.

## Interaction with Inference (Polyglot)

`ToolCallingDriver` sends tool schemas to the `polyglot` Inference layer using `withTools(array $tools)`. This layer adapts messages and tool calls to provider‑specific formats.

## Advanced

- Options: `ToolUseOptions` is an immutable container for future policy/config parameters (max steps/tokens/retries/timeouts, etc.).
- Typed Collections: internal collections are used for processors and continuation criteria.

## Known Limitations / Deferred Work

- Parallel tool execution: currently left as-is; future investigation required.
- Context variables: current `AppendContextVariables` remains; alternatives will be considered in a future major version.
- Tool schema value objects: kept as arrays at the boundary to avoid breaking `polyglot`; wrappers may be added later.

## Troubleshooting

- If tools appear not to run, ensure your tool names match the LLM tool call names.
- If a tool fails due to missing parameters, the follow‑up tool message will include an error; the loop will continue per your criteria (e.g., retries).

