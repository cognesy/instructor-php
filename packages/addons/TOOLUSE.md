# ToolUse Component

ToolUse orchestrates LLM tool-calling loops: it asks an LLM for tool calls, executes tools, appends follow‑up messages, and repeats until continuation criteria indicate completion.

This document explains how to use ToolUse, customize its behavior, and integrate it into an agent system.

## Overview

- Orchestrator: `Cognesy\Addons\ToolUse\ToolUse`
- Drivers:
  - `Drivers\ToolCallingDriver` — Provider tool-calling mode via Polyglot Inference (clean orchestration; pending inference, response, executions, follow‑ups)
  - `Drivers\ReAct\ReActDriver` — ReAct loop using StructuredOutput (typed decision extraction, optional plain‑text finalization)
- State: `ToolUseState` (messages, tools, usage, variables, steps, status)
- Step: `ToolUseStep` (LLM response + tool calls/executions + follow‑up messages + usage)
- Tools: `Tools` registry of `ToolInterface` implementations (`FunctionTool`, or custom tools)
- Processors: post‑step processors (accumulate usage, update step, etc.)
- Continuation criteria: stop/continue logic per step (steps/tokens/time/retries/finish reason)
- Observers: step‑level (`ToolUseObserver`) and tool‑level (`ToolsObserver`) hooks

## Quick Start

```php
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;use Cognesy\Addons\ToolUse\Tools\FunctionTool;use Cognesy\Addons\ToolUse\ToolUse;use Cognesy\Polyglot\Inference\LLMProvider;

function add_numbers(int $a, int $b): int { return $a + $b; }

$driver = new ToolCallingDriver(llm: LLMProvider::new());
$toolUse = (new ToolUse)
    ->withDriver($driver)
    ->withMessages('Add numbers 2 and 3')
    ->withTools(FunctionTool::fromCallable(add_numbers(...)));

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
use Cognesy\Utils\Result\Failure;

final class GetTime extends BaseTool {
    protected string $name = 'get_time';
    protected string $description = 'Returns current timestamp';
    public function __invoke(): int { return time(); }
}
```

Notes:
- Tool schemas are generated via `StructureFactory` from the `__invoke` signature.
- Return values are wrapped in `Result::success(...)` or `Result::failure(...)` at the tool boundary.

## Quick Start (ReAct)

```php
use Cognesy\Addons\ToolUse\ContinuationCriteria\{ExecutionTimeLimit,RetryLimit,StepsLimit,TokenUsageLimit};use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;use Cognesy\Addons\ToolUse\Drivers\ReAct\StopOnFinalDecision;use Cognesy\Addons\ToolUse\ToolUse;use Cognesy\Polyglot\Inference\LLMProvider;

$driver = new ReActDriver(
  llm: LLMProvider::using('openai'),
  maxRetries: 2,
  finalViaInference: true
);

$criteria = [new StepsLimit(6), new TokenUsageLimit(8192), new ExecutionTimeLimit(60), new RetryLimit(2), new StopOnFinalDecision()];

$toolUse = (new ToolUse(continuationCriteria: $criteria))
  ->withDriver($driver)
  ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
  ->withTools([ /* FunctionTool::fromCallable(...) */ ]);

$final = $toolUse->finalStep();
echo $final->response();
```

Notes:
- ReAct driver extracts a typed decision (call_tool | final_answer) using StructuredOutput with retries.
- For `final_answer` you can optionally finalize via Inference (plain-text).
- Use `StopOnFinalDecision` to stop iteration when the model decides to finalize.

## Failure Handling

- Default: Tool failures do not throw. Instead, `Result::failure(...)` is recorded in `ToolExecution` and a follow‑up error message is appended for the LLM.
- To restore legacy exception behavior for tool executions:

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

ReAct-specific:
- Recommended set excludes `ToolCallPresenceCheck` and includes `StopOnFinalDecision`.

## Step Processors

Processors post‑process each step (accumulate usage, update step list, append follow‑up messages). Defaults:
- `AccumulateTokenUsage`
- `UpdateStep`
- `AppendContextMetadata` (current behavior retained)
- `AppendStepMessages`

Add or override:

```php
$toolUse->withProcessors(new MyCustomProcessor());
```

`AccumulateTokenUsage` aggregates per-step usage into state, enabling per-step and total token reporting.

## Observability

Use the events.

## Argument Validation

Before invoking a tool, `Tools` performs a pragmatic check against the tool’s JSON schema and returns a failure if required parameters are missing. The failure is surfaced to the LLM through a tool error message.

## Interaction with Inference (Polyglot)

`ToolCallingDriver` uses pending inference (create → response) and executes provider-returned tool calls. `ReActDriver` uses StructuredOutput (for typed decision extraction with retries) and optionally Inference (for plain‑text final answer). Both rely on Polyglot to adapt requests/responses to providers.

## Advanced

- Options: `ToolUseOptions` is an immutable container for future policy/config parameters.
- Typed Collections: `StepProcessors` and `ContinuationCriteria` collections keep orchestration readable.
- Observability: `ToolUseObserver` (step-level) and `ToolsObserver` (tool-level).
- Monadic errors: failures become `Result::failure(...)` in `ToolExecution` and are surfaced as error messages to the LLM.

## Known Limitations / Deferred Work

- Parallel tool execution: currently left as-is; future investigation required.
- Context metadata: current `AppendContextMetadata` remains; alternatives will be considered in a future major version.
- Tool schema value objects: kept as arrays at the boundary to avoid breaking `polyglot`; wrappers may be added later.

## State & Reuse

- `ToolUse` is stateful: it holds a `ToolUseState` that accumulates messages, steps, usage, variables, and status.
- Preferred: create a fresh `ToolUse` per run.
- If reusing the same instance, reset state via `withState(new ToolUseState())` and reapply tools/messages.
- Drivers (`ToolCallingDriver`, `ReActDriver`) are stateless (configuration only) and safe to reuse across runs.

## Troubleshooting

- If tools appear not to run, ensure your tool names match the LLM tool call names.
- If a tool fails due to missing parameters, the follow‑up tool message will include an error; the loop will continue per your criteria (e.g., retries).
