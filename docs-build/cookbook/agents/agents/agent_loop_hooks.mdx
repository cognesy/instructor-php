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
- `HookStack`: Registers hooks directly on `AgentLoop` via `withInterceptor()`

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Bash\BashTool;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentConsoleLogger;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Hook\Hooks\CallableHook;

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

$hooks = (new HookStack(new RegisteredHooks()))
    // Hook 1: Before each step — inject timing metadata
    ->with(
        hook: new CallableHook(function (HookContext $ctx) use (&$timings): HookContext {
            $step = $ctx->state()->stepCount() + 1;
            $timings[$step] = microtime(true);
            return $ctx->withState(
                $ctx->state()->withMetadata('step_started_at', microtime(true))
            );
        }),
        triggerTypes: HookTriggers::beforeStep(),
        name: 'timing:start',
    )
    // Hook 2: After each step — calculate duration
    ->with(
        hook: new CallableHook(function (HookContext $ctx) use (&$timings): HookContext {
            $step = $ctx->state()->stepCount();
            $started = $timings[$step] ?? null;
            $duration = $started ? round((microtime(true) - $started) * 1000) : 0;
            $tokens = $ctx->state()->usage()->total();
            echo "  [timing] Step {$step}: {$duration}ms (total tokens: {$tokens})\n";
            return $ctx;
        }),
        triggerTypes: HookTriggers::afterStep(),
        name: 'timing:end',
    )
    // Hook 3: Before tool use — log which tool is about to run
    ->with(
        hook: new CallableHook(function (HookContext $ctx): HookContext {
            $toolName = $ctx->toolCall()?->name() ?? 'unknown';
            echo "  [audit] About to execute: {$toolName}\n";
            return $ctx;
        }),
        triggerTypes: HookTriggers::beforeToolUse(),
        name: 'audit:tool',
    )
    // Hook 4: After tool use — log tool result status
    ->with(
        hook: new CallableHook(function (HookContext $ctx): HookContext {
            $exec = $ctx->toolExecution();
            if ($exec !== null) {
                $status = $exec->wasBlocked() ? 'BLOCKED' : 'OK';
                echo "  [audit] Tool {$exec->name()} -> {$status}\n";
            }
            return $ctx;
        }),
        triggerTypes: HookTriggers::afterToolUse(),
        name: 'audit:result',
    )
    // Hook 5: On stop — final summary
    ->with(
        hook: new CallableHook(function (HookContext $ctx): HookContext {
            $state = $ctx->state();
            echo "  [summary] Agent stopping after {$state->stepCount()} steps\n";
            return $ctx;
        }),
        triggerTypes: HookTriggers::onStop(),
        name: 'summary',
    );

$agent = AgentLoop::default()
    ->withTool(BashTool::inDirectory(getcwd()))
    ->withInterceptor($hooks)
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

// Assertions
assert(!empty($finalState->finalResponse()->toString()), 'Expected non-empty response');
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
assert(!empty($timings), 'Expected timing data from hooks');
?>
```
