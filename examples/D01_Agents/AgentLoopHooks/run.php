---
title: 'Agent Lifecycle Hooks'
docname: 'agent_loop_hooks'
order: 5
id: '407a'
---
## Overview

Hooks intercept agent lifecycle events to observe or modify state. Each hook receives
a `HookContext` and returns a (potentially modified) `HookContext`. Hooks can:

- **Observe**: Log events, collect metrics without changing state
- **Modify**: Inject metadata, adjust system prompts, transform messages
- **Block**: Prevent tool execution (for `BeforeToolUse` hooks)

Key concepts:
- `CallableHook`: Wraps a closure as a `HookInterface`
- `HookTriggers`: Specifies when the hook fires (e.g., `beforeStep()`, `afterStep()`)
- `HookContext`: Carries `AgentState`, tool call info, and trigger type
- `addHook()`: Registers hooks on `AgentBuilder` with priority ordering

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Bash\UseBash;
use Cognesy\Agents\Broadcasting\AgentConsoleLogger;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Hooks\Collections\HookTriggers;
use Cognesy\Agents\Hooks\Defaults\CallableHook;
use Cognesy\Agents\Hooks\Data\HookContext;

// AgentConsoleLogger shows execution lifecycle alongside hook output
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
    showHooks: true,
);

// Track timing data
$timings = [];

$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withMaxSteps(5)

    // Hook 1: Before each step — inject timing metadata
    ->addHook(
        hook: new CallableHook(function (HookContext $ctx) use (&$timings): HookContext {
            $step = $ctx->state()->stepCount() + 1;
            $timings[$step] = microtime(true);
            return $ctx->withState(
                $ctx->state()->withMetadata('step_started_at', microtime(true))
            );
        }),
        triggers: HookTriggers::beforeStep(),
        name: 'timing:start',
    )

    // Hook 2: After each step — calculate duration
    ->addHook(
        hook: new CallableHook(function (HookContext $ctx) use (&$timings): HookContext {
            $step = $ctx->state()->stepCount();
            $started = $timings[$step] ?? null;
            $duration = $started ? round((microtime(true) - $started) * 1000) : 0;
            $tokens = $ctx->state()->usage()->total();
            echo "  [timing] Step {$step}: {$duration}ms (total tokens: {$tokens})\n";
            return $ctx;
        }),
        triggers: HookTriggers::afterStep(),
        name: 'timing:end',
    )

    // Hook 3: Before tool use — log which tool is about to run
    ->addHook(
        hook: new CallableHook(function (HookContext $ctx): HookContext {
            $toolName = $ctx->toolCall()?->name() ?? 'unknown';
            echo "  [audit] About to execute: {$toolName}\n";
            return $ctx;
        }),
        triggers: HookTriggers::beforeToolUse(),
        name: 'audit:tool',
    )

    // Hook 4: After tool use — log tool result status
    ->addHook(
        hook: new CallableHook(function (HookContext $ctx): HookContext {
            $exec = $ctx->toolExecution();
            if ($exec !== null) {
                $status = $exec->wasBlocked() ? 'BLOCKED' : 'OK';
                echo "  [audit] Tool {$exec->name()} -> {$status}\n";
            }
            return $ctx;
        }),
        triggers: HookTriggers::afterToolUse(),
        name: 'audit:result',
    )

    // Hook 5: On stop — final summary
    ->addHook(
        hook: new CallableHook(function (HookContext $ctx): HookContext {
            $state = $ctx->state();
            echo "  [summary] Agent stopping after {$state->stepCount()} steps\n";
            return $ctx;
        }),
        triggers: HookTriggers::onStop(),
        name: 'summary',
    )

    ->build()
    ->wiretap($logger->wiretap());

// Run the agent
$state = AgentState::empty()->withUserMessage(
    'What is the current date? Use bash to find out. Be concise.'
);

echo "=== Agent Execution with Hooks ===\n\n";
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
?>
```
