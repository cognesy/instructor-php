---
title: 'Agent Control Streaming'
docname: 'agent_ctrl_streaming'
id: 'b0bc'
---
## Overview

Streaming execution provides real-time visibility into agent operations. Instead of waiting
for completion, you see text output and tool calls as they happen. Combine streaming callbacks
with the `AgentCtrlConsoleLogger` for full execution introspection.

Key concepts:
- `executeStreaming()`: Execute with real-time output instead of waiting for completion
- `onText()`: Callback for each text chunk as it arrives
- `onToolUse()`: Callback for each tool call with inputs and outputs
- `AgentCtrlConsoleLogger`: Shows execution lifecycle alongside streaming output
- `withMaxTurns()`: Limit the number of agent loop iterations

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

// Console logger for execution lifecycle visibility
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,
);

$toolCalls = [];

echo "=== Agent Execution Log ===\n\n";

$response = AgentCtrl::claudeCode()
    ->wiretap($logger->wiretap())
    ->withMaxTurns(10)
    ->onText(function (string $text) {
        // Stream text as it arrives
        echo $text;
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) use (&$toolCalls) {
        // Show each tool call as it happens
        $target = $input['pattern'] ?? $input['file_path'] ?? $input['command'] ?? '';
        if (strlen($target) > 40) {
            $target = '...' . substr($target, -37);
        }
        $toolCalls[] = $tool;
        echo "\n  >> [{$tool}] {$target}\n";
    })
    ->executeStreaming('Find the AgentCtrl class and explain the make() factory method. Be concise.');

echo "\n=== Result ===\n";
echo "Tools used: " . implode(' > ', $toolCalls) . "\n";
echo "Total tool calls: " . count($toolCalls) . "\n";

if ($response->usage) {
    echo "Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
}
if ($response->cost) {
    echo "Cost: $" . number_format($response->cost, 4) . "\n";
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [claude-code] [EXEC] Execution started [model=claude-sonnet-4-5-20250514, prompt=Find the AgentCtrl class...]
14:32:15.234 [claude-code] [PROC] Process started [commands=1]
I'll search for the AgentCtrl class to understand the factory pattern.
14:32:15.456 [claude-code] [TOOL] Glob {pattern=src/**/*AgentCtrl*.php}

  >> [Glob] src/**/*AgentCtrl*.php

I found the AgentCtrl class. Let me examine the make() method.
14:32:16.234 [claude-code] [TOOL] Read {file_path=src/AgentCtrl/AgentCtrl.php}

  >> [Read] src/AgentCtrl/AgentCtrl.php

The make() factory method provides a clean way to instantiate agents by:
1. Accepting an AgentType enum to specify which CLI agent to use
2. Returning a configured AgentCtrl instance
3. Allowing method chaining for further configuration
14:32:17.890 [claude-code] [DONE] Execution completed [exit=0, tools=2, cost=$0.0021, tokens=214]

=== Result ===
Tools used: Glob > Read
Total tool calls: 2
Tokens: 125 in / 89 out
Cost: $0.0021
```

## Key Points

- **Real-time output**: Text appears as the agent generates it, not after completion
- **Console logger**: `AgentCtrlConsoleLogger` shows execution lifecycle alongside streaming output
- **Tool visibility**: See each tool call with its arguments as it executes
- **Progress tracking**: Know what the agent is doing at any moment
- **Same response**: Final response object has same structure as non-streaming execution
- **Fluent interface**: Combine `wiretap()`, `onText()`, `onToolUse()`, and configuration methods
- **Use cases**: Progress bars, interactive UIs, debugging, long-running tasks
