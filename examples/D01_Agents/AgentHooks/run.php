---
title: 'Agent Hooks - Tool Interception'
docname: 'agent_hooks'
---

## Overview

Hooks allow you to intercept tool calls before and after execution. This example
demonstrates using `onBeforeToolUse` to block dangerous bash commands - a practical
security pattern for agentic applications.

Key concepts:
- `onBeforeToolUse`: Intercept tool calls before execution
- `ToolHookContext`: Provides access to tool call and agent state
- `HookOutcome`: Control flow - proceed, block, or stop
- `matcher`: Filter which tools the hook applies to (supports wildcards and regex)
- `priority`: Control execution order (higher = runs first)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use Cognesy\Addons\Agent\Hooks\Data\ToolHookContext;

// Dangerous patterns to block
$blockedPatterns = [
    'rm -rf',
    'rm -r /',
    'sudo rm',
    '> /dev/sda',
    'mkfs',
    'dd if=',
    ':(){:|:&};:',  // Fork bomb
];

// Build agent with bash capability and security hook
$agent = AgentBuilder::new()
    ->withCapability(new UseBash())
    ->onBeforeToolUse(
        callback: function (ToolHookContext $ctx) use ($blockedPatterns): HookOutcome {
            $command = $ctx->toolCall()->args()['command'] ?? '';

            // Check for dangerous patterns
            foreach ($blockedPatterns as $pattern) {
                if (str_contains($command, $pattern)) {
                    echo "[BLOCKED] Dangerous command detected: {$command}\n";
                    return HookOutcome::block("Dangerous command: {$pattern}");
                }
            }

            echo "[ALLOWED] Executing: {$command}\n";
            return HookOutcome::proceed();
        },
        matcher: 'bash',     // Only apply to bash tool
        priority: 100,       // High priority = runs first
    )
    ->build();

// Test with safe commands
$state = AgentState::empty()->withUserMessage(
    'List the files in the current directory and show the date'
);

echo "=== Testing safe commands ===\n";
$finalState = $agent->finalStep($state);

$response = $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';
echo "\nAgent response:\n{$response}\n";

// Test with dangerous command (simulated prompt)
echo "\n=== Testing dangerous command detection ===\n";
$state2 = AgentState::empty()->withUserMessage(
    'Delete all files with: rm -rf /'
);

$finalState2 = $agent->finalStep($state2);

// The dangerous command should have been blocked
$hasErrors = $finalState2->currentStep()?->hasErrors() ?? false;
echo "Command was " . ($hasErrors ? "blocked (safe!)" : "executed") . "\n";
?>
```

## How It Works

1. **Hook Registration**: `onBeforeToolUse` registers a callback that runs before any tool execution
2. **Context Access**: `ToolHookContext` provides `toolCall()` and `state()` accessors
3. **Matcher**: The `'bash'` matcher ensures the hook only applies to the bash tool
4. **Priority**: Higher priority (100) ensures this security check runs before other hooks
5. **Blocking**: `HookOutcome::block($reason)` blocks the tool call with a reason
6. **Allowing**: `HookOutcome::proceed()` allows execution to proceed

## Matcher Patterns

The matcher supports several patterns:
- Exact: `'bash'` - matches only the "bash" tool
- Wildcard: `'read_*'` - matches "read_file", "read_stdin", etc.
- All: `'*'` - matches all tools
- Regex: `'/^(read|write)_.+$/'` - matches using regex

## Other Hook Types

```php
// After tool execution - for logging/metrics
->onAfterToolUse(
    callback: function (ToolHookContext $ctx): HookOutcome {
        $exec = $ctx->execution();
        $duration = $exec->endedAt()->getTimestamp() - $exec->startedAt()->getTimestamp();
        echo "Tool {$ctx->toolCall()->name()} took {$duration}s\n";
        return HookOutcome::proceed();
    },
)

// Before each step
->onBeforeStep(fn(AgentState $state) => $state->withMetadata('step_started', microtime(true)))

// After each step
->onAfterStep(function (AgentState $state): AgentState {
    $started = $state->metadata()->get('step_started');
    $duration = microtime(true) - $started;
    echo "Step took {$duration}s\n";
    return $state;
})
```
