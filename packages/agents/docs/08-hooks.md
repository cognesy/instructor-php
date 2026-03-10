---
title: 'Hooks'
description: 'Intercept agent lifecycle events for logging, guards, state modification, and tool blocking'
---

# Hooks

## Introduction

Hooks let you intercept every phase of the agent's execution lifecycle. They are the primary extension mechanism for cross-cutting concerns -- logging, rate limiting, safety guards, telemetry, state transformation, and tool access control.

Each hook receives a `HookContext` containing the current agent state and trigger-specific data, processes it, and returns a (potentially modified) context to continue the pipeline. Because both `HookContext` and `AgentState` are immutable, hooks compose safely -- each hook in the chain works with the output of the previous one, and no hook can accidentally corrupt shared state.

> **Design Philosophy:** Hooks follow the middleware pattern common in web frameworks, but adapted for agent execution. Instead of intercepting HTTP requests, hooks intercept the agent's internal lifecycle events -- giving you the same power to observe, modify, or short-circuit execution at precisely the right moment.

<a name="lifecycle-events"></a>
## Lifecycle Events

The agent loop emits eight trigger types at well-defined points during execution. Each trigger corresponds to a specific moment in the loop's lifecycle, and understanding when each fires is essential for placing your hooks correctly:

| Trigger | When It Fires | Available Data |
|---------|---------------|----------------|
| `BeforeExecution` | Once, before the loop begins its first step | Agent state |
| `BeforeStep` | Before each LLM call | Agent state |
| `BeforeToolUse` | Before each individual tool execution | Agent state, `ToolCall` |
| `AfterToolUse` | After each individual tool execution | Agent state, `ToolExecution` |
| `AfterStep` | After each loop iteration completes | Agent state |
| `OnStop` | When the loop detects a stop condition | Agent state |
| `AfterExecution` | Once, after the loop ends | Agent state |
| `OnError` | When an error occurs during execution | Agent state, `ErrorList` |

These triggers are defined in the `HookTrigger` enum:

```php
use Cognesy\Agents\Hook\Enums\HookTrigger;

HookTrigger::BeforeExecution;   // 'before_execution'
HookTrigger::BeforeStep;        // 'before_step'
HookTrigger::BeforeToolUse;     // 'before_tool_use'
HookTrigger::AfterToolUse;      // 'after_tool_use'
HookTrigger::AfterStep;         // 'after_step'
HookTrigger::OnStop;            // 'on_stop'
HookTrigger::AfterExecution;    // 'after_execution'
HookTrigger::OnError;           // 'on_error'
```

The following diagram illustrates the typical flow through these triggers during a single execution:

```
BeforeExecution
  |
  +---> BeforeStep
  |       |
  |       +---> [LLM Call]
  |       |
  |       +---> BeforeToolUse ---> [Tool Execution] ---> AfterToolUse
  |       |       (repeated for each tool call in the step)
  |       |
  |       +---> AfterStep
  |       |
  |       +---> (loop back to BeforeStep if not stopping)
  |
  +---> OnStop (when stop condition detected)
  |
  +---> AfterExecution
```

If an error occurs at any point, the `OnError` trigger fires with the accumulated error information.

<a name="implementing-hooks"></a>
## Implementing a Hook

Create a class that implements `HookInterface`. The `handle` method receives a `HookContext` and must return one -- either the original context unchanged, or a modified copy:

```php
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

class LogStepsHook implements HookInterface
{
    public function handle(HookContext $context): HookContext
    {
        $steps = $context->state()->stepCount();
        echo "Step {$steps} | Trigger: {$context->triggerType()->value}\n";
        return $context;
    }
}
```

<a name="hook-context"></a>
### Understanding HookContext

The `HookContext` object provides access to different data depending on the trigger type. It serves as both the input and output of hook processing, carrying all the information a hook needs to make decisions:

| Method | Return Type | Description | Available On |
|--------|-------------|-------------|--------------|
| `state()` | `AgentState` | The current agent state with full access to context, messages, metadata, and execution data | All triggers |
| `triggerType()` | `HookTrigger` | The enum value identifying which lifecycle event fired this hook | All triggers |
| `toolCall()` | `?ToolCall` | The tool call about to be executed, including the tool name and arguments | `BeforeToolUse` |
| `toolExecution()` | `?ToolExecution` | The completed tool execution result, including output and status | `AfterToolUse` |
| `errorList()` | `ErrorList` | Accumulated errors from the execution | `OnError` (primarily) |
| `metadata()` | `mixed` | Arbitrary metadata passed with the trigger; accepts an optional key and default value | All triggers |
| `createdAt()` | `DateTimeImmutable` | When this hook context was created | All triggers |
| `updatedAt()` | `DateTimeImmutable` | When this hook context was last modified by a hook | All triggers |
| `hasErrors()` | `bool` | Whether the error list contains any errors | All triggers |
| `isToolExecutionBlocked()` | `bool` | Whether tool execution has been blocked by a hook | `BeforeToolUse` |

`HookContext` also provides convenient named constructors for each trigger type, used internally by the agent loop:

```php
// These are used by the loop -- you typically don't call them directly
$ctx = HookContext::beforeExecution($state);
$ctx = HookContext::beforeStep($state);
$ctx = HookContext::beforeToolUse($state, $toolCall);
$ctx = HookContext::afterToolUse($state, $toolExecution);
$ctx = HookContext::afterStep($state);
$ctx = HookContext::onStop($state);
$ctx = HookContext::afterExecution($state);
$ctx = HookContext::onError($state, $errorList);
```

<a name="registering-hooks"></a>
## Registering Hooks

### Via AgentBuilder (Recommended)

The `UseHook` capability provides a declarative way to register hooks during agent construction. Each `UseHook` instance binds a hook implementation to one or more triggers with a specified priority:

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Hook\Collections\HookTriggers;

$agent = AgentBuilder::base()
    ->withCapability(new UseHook(
        hook: new LogStepsHook(),
        triggers: HookTriggers::afterStep(),
        priority: 10,
        name: 'log_steps',
    ))
    ->build();
```

A hook can listen to multiple triggers by combining them with `HookTriggers::of()`:

```php
use Cognesy\Agents\Hook\Enums\HookTrigger;

$agent = AgentBuilder::base()
    ->withCapability(new UseHook(
        hook: new MyHook(),
        triggers: HookTriggers::of(
            HookTrigger::BeforeStep,
            HookTrigger::AfterStep,
        ),
    ))
    ->build();
```

The `HookTriggers` class provides convenience constructors for every trigger type, as well as the ability to combine them:

```php
HookTriggers::all();             // Every trigger type
HookTriggers::none();            // No triggers (useful for conditional registration)
HookTriggers::beforeExecution(); // Just BeforeExecution
HookTriggers::beforeStep();      // Just BeforeStep
HookTriggers::beforeToolUse();   // Just BeforeToolUse
HookTriggers::afterToolUse();    // Just AfterToolUse
HookTriggers::afterStep();       // Just AfterStep
HookTriggers::onStop();          // Just OnStop
HookTriggers::afterExecution();  // Just AfterExecution
HookTriggers::onError();         // Just OnError

// Combine multiple triggers
HookTriggers::of(HookTrigger::BeforeStep, HookTrigger::AfterStep);
```

### Via HookStack (Manual)

When composing an `AgentLoop` directly without the builder, assemble hooks into a `HookStack`. The `HookStack` wraps a `RegisteredHooks` collection and implements the `CanInterceptAgentLifecycle` interface, making it pluggable into the agent loop:

```php
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\HookStack;

$stack = new HookStack(new RegisteredHooks());
$stack = $stack->with(
    hook: new LogStepsHook(),
    triggerTypes: HookTriggers::afterStep(),
    priority: 10,
    name: 'log_steps',
);

$loop = AgentLoop::default()->withInterceptor($stack);
```

The `HookStack` is immutable -- each `with()` call returns a new instance with the hook added and the collection re-sorted by priority. You can chain multiple hooks fluently:

```php
$stack = $stack
    ->with($hookA, HookTriggers::beforeStep(), priority: 100)
    ->with($hookB, HookTriggers::afterStep(), priority: 50)
    ->with($hookC, HookTriggers::onError(), priority: 0);
```

You can also add a pre-built `RegisteredHook` directly:

```php
use Cognesy\Agents\Hook\Data\RegisteredHook;

$registeredHook = new RegisteredHook(
    hook: new LogStepsHook(),
    triggers: HookTriggers::afterStep(),
    priority: 10,
    name: 'log_steps',
);

$stack = $stack->withHook($registeredHook);
```

<a name="callable-hook"></a>
## CallableHook

For quick, one-off hooks that do not warrant a dedicated class, use `CallableHook` with a closure. This is particularly handy for prototyping or adding simple logging during development:

```php
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Hook\Data\HookContext;

$hook = new CallableHook(function (HookContext $ctx): HookContext {
    echo "Step completed.\n";
    return $ctx;
});

$agent = AgentBuilder::base()
    ->withCapability(new UseHook(
        hook: $hook,
        triggers: HookTriggers::afterStep(),
    ))
    ->build();
```

`CallableHook` accepts any `callable` that takes a `HookContext` and returns a `HookContext`. It converts the callable to a `Closure` internally for type safety.

<a name="hook-priority"></a>
## Hook Priority

When a trigger fires, hooks are executed in **descending priority order** -- higher values run first. This ordering is critical when hooks have dependencies on each other. For example, guard hooks that may emit stop signals should run before business logic hooks that assume the loop will continue.

The `RegisteredHooks` collection sorts hooks automatically when they are added. The sort is stable, so hooks with the same priority retain their registration order.

The built-in guard hooks use a priority of **200** (or **-200** for the finish reason guard, which runs on `AfterStep`), giving them precedence over custom hooks at the default priority of **0**. Choose your priorities according to the following guidelines:

| Range | Suggested Use | Examples |
|-------|---------------|----------|
| 200+ | Safety guards, resource limits | Step limits, token limits, time limits |
| 100-199 | Infrastructure concerns | Logging, telemetry, metrics collection |
| 0-99 | Business logic, custom behavior | State enrichment, conditional branching |
| Negative | Post-processing, cleanup | Finish reason detection, result formatting |

> **Tip:** When in doubt, use the default priority of 0. Only assign explicit priorities when you need guaranteed ordering between hooks.

<a name="modifying-state"></a>
## Modifying Agent State

Hooks can modify the agent's state by returning a `HookContext` with an updated `AgentState`. Since both objects are immutable, you create modified copies using the `with*` methods:

```php
$hook = new CallableHook(function (HookContext $ctx): HookContext {
    $state = $ctx->state()->withMetadata('processed_at', time());
    return $ctx->withState($state);
});
```

State modifications flow through the hook pipeline and back into the loop. This makes hooks suitable for:

- **Injecting context** -- adding metadata that downstream hooks or the driver can read
- **Adjusting system prompts** -- dynamically modifying the system prompt based on execution state
- **Attaching metadata** -- tagging the state with timestamps, user IDs, or feature flags
- **Modifying the message store** -- adding, removing, or transforming messages before the next LLM call

```php
// Example: Dynamically adjust the system prompt based on step count
$hook = new CallableHook(function (HookContext $ctx): HookContext {
    $state = $ctx->state();
    if ($state->stepCount() > 5) {
        $context = $state->context()->withSystemPrompt(
            $state->context()->systemPrompt() . "\n\nPlease wrap up your current task."
        );
        $state = $state->with(context: $context);
    }
    return $ctx->withState($state);
});
```

<a name="blocking-tools"></a>
## Blocking Tool Execution

In a `BeforeToolUse` hook, you can prevent a tool from executing by calling `withToolExecutionBlocked()` on the context. This is a powerful safety mechanism for restricting which tools the model can invoke at runtime:

```php
class BlockDangerousTools implements HookInterface
{
    private array $blockedTools = ['delete_all_data', 'drop_database', 'rm_rf'];

    public function handle(HookContext $context): HookContext
    {
        $toolName = $context->toolCall()?->name();

        if ($toolName !== null && in_array($toolName, $this->blockedTools, true)) {
            return $context->withToolExecutionBlocked(
                "Tool \"{$toolName}\" is not permitted in this environment."
            );
        }

        return $context;
    }
}
```

Register it on the `BeforeToolUse` trigger with a high priority to ensure it runs before other hooks:

```php
$agent = AgentBuilder::base()
    ->withCapability(new UseHook(
        hook: new BlockDangerousTools(),
        triggers: HookTriggers::beforeToolUse(),
        priority: 200,
        name: 'block_dangerous_tools',
    ))
    ->build();
```

When a tool is blocked, several things happen internally:

1. The `HookContext` is marked with `isToolExecutionBlocked = true`
2. A `ToolExecution` with blocked status is created and attached to the context
3. A `ToolExecutionBlockedException` is recorded in the error list
4. The loop skips the actual tool execution
5. The rejection message is fed back to the model as the tool result, so it can adjust its approach

You can also provide a custom message when blocking. If no message is provided, a default message is generated that includes details about the hook context for debugging:

```php
// With custom message (recommended for user-facing agents)
$context->withToolExecutionBlocked('This tool requires admin privileges.');

// With default message (includes HookContext details)
$context->withToolExecutionBlocked();
```

<a name="context-config"></a>
## Applying Context Configuration

The built-in `ApplyContextConfigHook` sets the system prompt and response format on the agent context at the start of execution. This is how the builder internally applies system prompt and response format settings configured through `UseContextConfig`:

```php
use Cognesy\Agents\Hook\Hooks\ApplyContextConfigHook;

$hook = new ApplyContextConfigHook(
    systemPrompt: 'You are a data analysis assistant.',
    responseFormat: $responseFormat,
);
```

This hook runs on `BeforeExecution` and modifies the `AgentContext` inside the state, ensuring the system prompt and format are in place before the first LLM call. It only applies non-empty values -- an empty system prompt or a `null` / empty response format will leave the existing context values unchanged.

<a name="guard-hooks"></a>
## Built-in Guard Hooks

Guard hooks enforce resource limits by emitting stop signals when thresholds are exceeded. They are the primary mechanism for preventing runaway agents that might otherwise consume unlimited tokens, time, or steps.

### UseGuards Capability

The `UseGuards` capability bundles all four guards with sensible defaults, providing a convenient one-liner for common resource protection:

```php
use Cognesy\Agents\Capability\Core\UseGuards;

$agent = AgentBuilder::base()
    ->withCapability(new UseGuards(
        maxSteps: 10,
        maxTokens: 5000,
        maxExecutionTime: 30.0,
        finishReasons: [],
    ))
    ->build();
```

Each parameter is optional and nullable -- pass `null` to disable a specific guard. The defaults are:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `maxSteps` | `20` | Maximum number of loop iterations |
| `maxTokens` | `32768` | Maximum cumulative token usage across all LLM calls |
| `maxExecutionTime` | `300.0` | Maximum wall-clock seconds for the entire execution |
| `finishReasons` | `[]` | LLM finish reasons that should trigger a stop (empty = disabled) |

### Individual Guard Hooks

You can also register guards individually for finer control over triggers, priorities, and configuration.

#### StepsLimitHook

Stops the loop after a maximum number of steps. It accepts a callable `stepCounter` that extracts the current step count from the agent state, making it flexible enough to count different things (e.g., total steps, steps within the current execution):

```php
use Cognesy\Agents\Hook\Hooks\StepsLimitHook;

$guard = new StepsLimitHook(
    maxSteps: 10,
    stepCounter: fn($state) => $state->stepCount(),
);
```

When the limit is reached, it emits a `StopSignal` with reason `StepsLimitReached` and a descriptive message like `"Step limit reached: 10/10"`.

#### TokenUsageLimitHook

Stops the loop when cumulative token usage (input + output tokens across all LLM calls) exceeds a threshold. Token usage is tracked automatically by the agent state through the `usage()` accessor:

```php
use Cognesy\Agents\Hook\Hooks\TokenUsageLimitHook;

$guard = new TokenUsageLimitHook(maxTotalTokens: 5000);
```

When the limit is reached, it emits a `StopSignal` with reason `TokenLimitReached`.

#### ExecutionTimeLimitHook

Stops the loop after a wall-clock duration. Unlike other guards, this hook needs to listen to **two** triggers: `BeforeExecution` to record the start time, and `BeforeStep` to check elapsed time before each LLM call:

```php
use Cognesy\Agents\Hook\Hooks\ExecutionTimeLimitHook;
use Cognesy\Agents\Hook\Enums\HookTrigger;

$guard = new ExecutionTimeLimitHook(maxSeconds: 30.0);

// Must be registered on both triggers
$stack = $stack->with(
    $guard,
    HookTriggers::of(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
    priority: 200,
);
```

The hook uses microsecond-precision timestamps (`DateTimeImmutable` with `U.u` format) for accurate timing. When the limit is reached, it emits a `StopSignal` with reason `TimeLimitReached`.

> **Note:** The `UseGuards` capability handles the dual-trigger registration automatically. You only need to manage it manually when registering the hook directly.

#### FinishReasonHook

Stops the loop when the LLM's finish reason matches a specified set. This is useful for stopping when the model indicates it has finished naturally (e.g., `stop` finish reason) rather than being cut off by a token limit. It runs on `AfterStep` since the finish reason is only available after the model responds:

```php
use Cognesy\Agents\Hook\Hooks\FinishReasonHook;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

$guard = new FinishReasonHook(
    stopReasons: [InferenceFinishReason::Stop],
    finishReasonResolver: fn($state) => $state->currentStep()?->finishReason(),
);
```

When registered through `UseGuards`, this hook receives a priority of **-200** (running after other `AfterStep` hooks) to ensure all post-step processing has completed before checking the finish reason.

<a name="execution-flow"></a>
## How Hooks Execute

When a trigger fires, the `HookStack` iterates through all registered hooks sorted by priority (descending). Each hook that matches the trigger type receives the `HookContext`, processes it, and returns a (potentially modified) context. The returned context flows into the next hook in the chain:

```
Trigger fires
  -> Hook A (priority 200) -> modified context
  -> Hook B (priority 100) -> modified context
  -> Hook C (priority 0)   -> final context
  -> Loop continues with final context
```

Hooks that do not match the current trigger type are silently skipped. Each successful hook execution dispatches a `HookExecuted` event containing the trigger type, hook name, and execution timestamp -- enabling external observability and performance monitoring.

The `HookStack` implements `CanInterceptAgentLifecycle`, meaning it can be replaced entirely with a custom interception strategy. The `PassThroughInterceptor` is a no-op implementation that returns the context unchanged, useful for testing or when you want to disable all hooks:

```php
use Cognesy\Agents\Interception\PassThroughInterceptor;

$loop = AgentLoop::default()->withInterceptor(new PassThroughInterceptor());
```

<a name="practical-examples"></a>
## Practical Examples

### Audit Trail Hook

Record every tool invocation for compliance or debugging:

```php
class AuditTrailHook implements HookInterface
{
    private array $log = [];

    public function handle(HookContext $context): HookContext
    {
        if ($context->triggerType() === HookTrigger::AfterToolUse) {
            $execution = $context->toolExecution();
            $this->log[] = [
                'tool' => $execution->name(),
                'timestamp' => $context->createdAt()->format('c'),
                'blocked' => $execution->wasBlocked(),
            ];
        }

        return $context;
    }

    public function getLog(): array
    {
        return $this->log;
    }
}
```

### Rate Limiting Hook

Throttle tool calls to prevent excessive API usage:

```php
class RateLimitHook implements HookInterface
{
    private int $callCount = 0;

    public function __construct(
        private int $maxCallsPerExecution = 50,
    ) {}

    public function handle(HookContext $context): HookContext
    {
        if ($context->triggerType() === HookTrigger::BeforeToolUse) {
            $this->callCount++;

            if ($this->callCount > $this->maxCallsPerExecution) {
                return $context->withToolExecutionBlocked(
                    "Rate limit exceeded: {$this->callCount}/{$this->maxCallsPerExecution} tool calls."
                );
            }
        }

        return $context;
    }
}
```

### Conditional Tool Access

Allow or deny tools based on metadata (e.g., user role):

```php
class RoleBasedAccessHook implements HookInterface
{
    private array $adminOnlyTools = ['deploy', 'rollback', 'delete_user'];

    public function handle(HookContext $context): HookContext
    {
        $toolName = $context->toolCall()?->name();
        if ($toolName === null || !in_array($toolName, $this->adminOnlyTools, true)) {
            return $context;
        }

        $role = $context->state()->context()->metadata()->get('user_role');
        if ($role !== 'admin') {
            return $context->withToolExecutionBlocked(
                "Tool \"{$toolName}\" requires admin privileges."
            );
        }

        return $context;
    }
}
```
