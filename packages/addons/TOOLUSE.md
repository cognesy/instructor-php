# ToolUse Component

ToolUse orchestrates LLM tool-calling loops: it asks an LLM for tool calls, executes tools, appends follow‑up messages, and repeats until continuation criteria indicate completion.

This document explains how to use ToolUse, customize its behavior, and integrate it into an agent system.

## Overview

The ToolUse component extends the StepByStep framework to orchestrate iterative tool execution:

- **Factory**: `Cognesy\Addons\ToolUse\ToolUseFactory` — convenient factory with sensible defaults
- **Orchestrator**: `Cognesy\Addons\ToolUse\ToolUse` — extends StepByStep to process tool execution steps iteratively
- **Drivers**:
  - `Drivers\ToolCalling\ToolCallingDriver` — Provider-native tool calling via Polyglot Inference
  - `Drivers\ReAct\ReActDriver` — ReAct reasoning loop using StructuredOutput
- **State**: `ToolUseState` — immutable state (messages, usage, metadata, steps)
- **Step**: `ToolUseStep` — single iteration result (response, tool calls/executions, usage)
- **Tools**: `Tools` — registry managing `ToolInterface` implementations
  - `FunctionTool::fromCallable()` — wraps PHP functions/closures as tools
  - `BaseTool` — base class for custom tool implementations
- **State Processors**: `StateProcessors` — post-step processing pipeline using StepByStep framework
- **Continuation Criteria**: `ContinuationCriteria` — determines when to stop/continue using StepByStep framework
- **Events**: Comprehensive event system for observability

## Quick Start

```php
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;

function add_numbers(int $a, int $b): int { return $a + $b; }

$tools = new Tools(
    FunctionTool::fromCallable(add_numbers(...))
);

$toolUse = ToolUseFactory::default(tools: $tools);

$state = (new ToolUseState())
    ->withMessages(Messages::fromString('Add numbers 2 and 3'));

$final = $toolUse->finalStep($state);
echo $final->currentStep()?->outputMessages()?->toString() ?? '';
```

**Key concepts:**
- `ToolUseFactory::default()` provides sensible defaults with processors and continuation criteria
- `Tools` registry manages tool implementations (`FunctionTool::fromCallable()` for functions)
- State-based API: Create initial `ToolUseState` with messages, pass to `finalStep()` or iterate with `nextStep()`
- ToolUse extends StepByStep providing `nextStep()`, `hasNextStep()`, `finalStep()`, and `iterator()` methods

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
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Drivers\ReAct\StopOnFinalDecision;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

$driver = new ReActDriver(
    llm: LLMProvider::using('openai'),
    maxRetries: 2,
    finalViaInference: true
);

$continuationCriteria = new ContinuationCriteria(
    new StepsLimit(6, fn(ToolUseState $state) => $state->stepCount()),
    new TokenUsageLimit(8192, fn(ToolUseState $state) => $state->usage()->total()),
    new StopOnFinalDecision(),
);

$tools = new Tools(
    FunctionTool::fromCallable(add_numbers(...)),
    FunctionTool::fromCallable(subtract_numbers(...))
);

$toolUse = ToolUseFactory::default(
    tools: $tools,
    continuationCriteria: $continuationCriteria,
    driver: $driver
);

$state = (new ToolUseState())
    ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

$final = $toolUse->finalStep($state);
echo $final->currentStep()?->outputMessages()?->toString() ?? '';
```

Notes:
- ReAct driver extracts a typed decision (call_tool | final_answer) using StructuredOutput with retries.
- For `final_answer` you can optionally finalize via Inference (plain-text).
- Use `StopOnFinalDecision` to stop iteration when the model decides to finalize.

## Failure Handling

- Default: Tool failures do not throw. Instead, `Result::failure(...)` is recorded in `ToolExecution` and a follow‑up error message is appended for the LLM.
- To restore legacy exception behavior for tool executions:

```php
$toolExecutor = $toolUse->toolExecutor()->withThrowOnToolFailure(true);
$toolUse = $toolUse->withToolExecutor($toolExecutor);
```

## Continuation Criteria

Continuation criteria determine when the tool execution should stop. All criteria are part of the StepByStep framework.

Default criteria from `ToolUseFactory::defaultContinuationCriteria()`:
- `StepsLimit(3)` — maximum number of tool-use steps
- `TokenUsageLimit(8192)` — maximum total token usage
- `ExecutionTimeLimit(30)` — maximum execution time in seconds
- `RetryLimit(3)` — maximum consecutive failed steps
- `ErrorPresenceCheck()` — stop on any errors
- `ToolCallPresenceCheck()` — continue only if tool calls present
- `FinishReasonCheck([])` — stop when finish reason matches

Customize with factory or direct instantiation:

```php
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Cognesy\Addons\StepByStep\State\Contracts\HasUsage;

// Via factory with custom defaults
$toolUse = ToolUseFactory::default(
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(10, fn(HasSteps $state) => $state->stepCount()),
        new TokenUsageLimit(4096, fn(HasUsage $state) => $state->usage()->total()),
        // ... other criteria
    )
);

// Or modify existing instance
$toolUse = $toolUse->withContinuationCriteria(
    new StepsLimit(5, fn(HasSteps $state) => $state->stepCount())
);
```

ReAct-specific:
- Recommended set excludes `ToolCallPresenceCheck` and includes `StopOnFinalDecision`.

## State Processors

State processors handle state transformation after each step using the StepByStep framework's middleware pattern.

Default processors from `ToolUseFactory::defaultProcessors()`:
- `AccumulateTokenUsage` — aggregates token usage per step
- `AppendContextMetadata` — adds context metadata to messages
- `AppendStepMessages` — appends step output messages to state

Add or override processors:

```php
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;

// Via factory
$toolUse = ToolUseFactory::default(
    processors: new StateProcessors(
        new AccumulateTokenUsage(),
        new AppendStepMessages(),
        new MyCustomProcessor(),
    )
);

// Or modify existing instance
$toolUse = $toolUse->withProcessors(
    new AccumulateTokenUsage(),
    new AppendStepMessages(),
    new MyCustomProcessor()
);
```

## Observability

ToolUse emits events throughout the tool-calling lifecycle:

- `ToolUseStepStarted` — when a new step begins
- `ToolUseStepCompleted` — when a step completes
- `ToolUseFinished` — when the entire process completes
- `ToolCallStarted` — before a tool is executed
- `ToolCallCompleted` — after a tool finishes

Configure event handling via factory or constructor:

```php
use Cognesy\Events\EventBus;

$events = new EventBus();
$events->listen(ToolCallCompleted::class, function($event) {
    echo "Tool {$event->toolName} completed\n";
});

$toolUse = ToolUseFactory::default(events: $events);
```

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

## Iteration Patterns

ToolUse provides three ways to process tool calls:

### Pattern 1: Manual Control
```php
$state = (new ToolUseState())->withMessages(Messages::fromString('Calculate...'));

while ($toolUse->hasNextStep($state)) {
    $state = $toolUse->nextStep($state);
    $step = $state->currentStep();
    echo "Step: " . ($step?->outputMessages()?->toString() ?? 'No output') . "\n";
}
echo "Final: " . ($state->currentStep()?->outputMessages()?->toString() ?? 'No output') . "\n";
```

### Pattern 2: Iterator
```php
$state = (new ToolUseState())->withMessages(Messages::fromString('Calculate...'));

foreach ($toolUse->iterator($state) as $newState) {
    $step = $newState->currentStep();
    echo "Step: " . ($step?->outputMessages()?->toString() ?? 'No output') . "\n";
}
```

### Pattern 3: Direct Final Result
```php
$state = (new ToolUseState())->withMessages(Messages::fromString('Calculate...'));
$finalState = $toolUse->finalStep($state);
echo "Result: " . ($finalState->currentStep()?->outputMessages()?->toString() ?? 'No output') . "\n";
```

## State & Reuse

- `ToolUse` is stateless — takes `ToolUseState` and returns new states
- `ToolUseState` instances are immutable — safe to store/restore
- `ToolUseFactory` provides sensible defaults for quick setup
- Drivers are stateless and safe to reuse across conversations

## Troubleshooting

- If tools appear not to run, ensure your tool names match the LLM tool call names.
- If a tool fails due to missing parameters, the follow‑up tool message will include an error; the loop will continue per your criteria (e.g., retries).
