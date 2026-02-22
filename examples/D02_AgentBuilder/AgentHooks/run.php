---
title: 'Agent Hooks - Tool Interception'
docname: 'agent_hooks'
order: 4
id: '9185'
---
## Overview

Hooks allow you to intercept tool calls before and after execution. This example
demonstrates using a `BeforeToolUse` hook to block dangerous bash commands - a practical
security pattern for agentic applications.

Key concepts:
- `CallableHook`: Wraps a closure as a hook
- `HookContext`: Provides access to tool call and agent state
- `HookTriggers`: Defines when the hook fires (e.g., `beforeToolUse()`)
- `UseHook`: Registers a hook capability with explicit trigger/priority
- `AgentEventConsoleObserver`: Provides visibility into agent execution stages

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Hooks\CallableHook;

// Create console logger for execution visibility
$logger = new AgentEventConsoleObserver(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: false,  // We'll show args in our custom hook output
);

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
$blockedPatterns = array_map(
    static fn(string $pattern): string => strtolower(trim($pattern)),
    $blockedPatterns,
);

// Build agent with bash capability and security hook
$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withCapability(new UseHook(
        hook: new CallableHook(function (HookContext $ctx) use ($blockedPatterns): HookContext {
            $toolCall = $ctx->toolCall();
            if ($toolCall === null) {
                return $ctx;
            }

            $args = $toolCall->args();
            $rawCommand = match (true) {
                is_array($args) && isset($args['command']) && is_string($args['command']) => $args['command'],
                default => '',
            };
            $command = strtolower(trim((string) preg_replace('/\s+/', ' ', $rawCommand)));
            if ($command === '') {
                return $ctx;
            }

            // Check for dangerous patterns
            foreach ($blockedPatterns as $pattern) {
                if (str_contains($command, $pattern)) {
                    echo "         [HOOK] BLOCKED - Dangerous pattern detected: {$pattern}\n";
                    return $ctx->withToolExecutionBlocked("Dangerous command: {$pattern}");
                }
            }

            echo "         [HOOK] ALLOWED - {$rawCommand}\n";
            return $ctx;
        }),
        triggers: HookTriggers::beforeToolUse(),
        priority: 100,       // High priority = runs first
    ))
    ->withCapability(new UseGuards(maxSteps: 8, maxTokens: 4096, maxExecutionTime: 30))
    ->build()
    ->wiretap($logger->wiretap());

// Test with safe commands
$state = AgentState::empty()->withUserMessage(
    'List the files in the current directory and show the date'
);

echo "=== Test 1: Safe Commands ===\n\n";
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Status: {$finalState->status()->value}\n";

// Test with dangerous command (simulated prompt)
echo "\n=== Test 2: Dangerous Command Detection ===\n\n";
$state2 = AgentState::empty()->withUserMessage(
    'Delete all files with: rm -rf /'
);

$finalState2 = $agent->execute($state2);

echo "\n=== Result ===\n";
$hasErrors = $finalState2->currentStep()?->hasErrors() ?? false;
echo "Command was " . ($hasErrors ? "BLOCKED (security hook worked!)" : "executed") . "\n";
echo "Steps: {$finalState2->stepCount()}\n";
echo "Status: {$finalState2->status()->value}\n";

// Assertions
assert(!empty($finalState->finalResponse()->toString()), 'Expected non-empty response from safe commands');
assert($finalState->stepCount() >= 1, 'Expected at least 1 step for safe commands');
assert($finalState2->stepCount() >= 1, 'Expected at least 1 step for dangerous command test');
?>
```

## How It Works

1. **Hook Registration**: `UseHook` registers a `CallableHook` with `HookTriggers::beforeToolUse()`
2. **Context Access**: `HookContext` provides `toolCall()` and `state()` accessors
3. **Priority**: Higher priority (100) ensures this security check runs before other hooks
4. **Blocking**: `$ctx->withToolExecutionBlocked($reason)` blocks the tool call with a reason
5. **Allowing**: Returning `$ctx` unchanged allows execution to proceed

## Other Hook Types

```php
// After tool execution - for logging/metrics
->withCapability(new UseHook(
    hook: new CallableHook(function (HookContext $ctx): HookContext {
        $exec = $ctx->toolExecution();
        if ($exec !== null) {
            echo "Tool {$exec->name()} completed\n";
        }
        return $ctx;
    }),
    triggers: HookTriggers::afterToolUse(),
))

// Before each step - modify state
->withCapability(new UseHook(
    hook: new CallableHook(function (HookContext $ctx): HookContext {
        $state = $ctx->state()->withMetadata('step_started', microtime(true));
        return $ctx->withState($state);
    }),
    triggers: HookTriggers::beforeStep(),
))

// After each step
->withCapability(new UseHook(
    hook: new CallableHook(function (HookContext $ctx): HookContext {
        $started = $ctx->state()->metadata()->get('step_started');
        if ($started !== null) {
            $duration = microtime(true) - $started;
            echo "Step took {$duration}s\n";
        }
        return $ctx;
    }),
    triggers: HookTriggers::afterStep(),
))
```
