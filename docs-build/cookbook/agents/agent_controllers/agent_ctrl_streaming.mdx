---
title: 'Agent Control Streaming'
docname: 'agent_ctrl_streaming'
---

## Overview

Streaming execution provides real-time visibility into agent operations. Instead of waiting
for completion, you see text output and tool calls as they happen. This is essential for
long-running tasks, interactive applications, and user-facing interfaces.

Key concepts:
- `executeStreaming()`: Execute with real-time output instead of waiting for completion
- `onText()`: Callback for each text chunk as it arrives
- `onToolUse()`: Callback for each tool call with inputs and outputs
- `withMaxTurns()`: Limit the number of agent loop iterations

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;

$toolCalls = [];

$response = AgentCtrl::claudeCode()
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
        echo "\n  ⚡ [{$tool}] {$target}\n";
    })
    ->executeStreaming('Find the AgentCtrl class and explain the make() factory method. Be concise.');

// Summary after execution completes
echo "\n\nEXECUTION SUMMARY:\n";
echo "  Tools used: " . implode(' → ', $toolCalls) . "\n";
echo "  Total tool calls: " . count($toolCalls) . "\n";

if ($response->usage) {
    echo "  Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
}
if ($response->cost) {
    echo "  Cost: $" . number_format($response->cost, 4) . "\n";
}
?>
```

## Expected Output

```
I'll search for the AgentCtrl class to understand the factory pattern.

  ⚡ [Glob] src/**/*AgentCtrl*.php

I found the AgentCtrl class. Let me examine the make() method.

  ⚡ [Read] src/AgentCtrl/AgentCtrl.php

The make() factory method provides a clean way to instantiate agents by:
1. Accepting an AgentType enum to specify which CLI agent to use
2. Returning a configured AgentCtrl instance
3. Allowing method chaining for further configuration

EXECUTION SUMMARY:
  Tools used: Glob → Read
  Total tool calls: 2
  Tokens: 125 in / 89 out
  Cost: $0.0021
```

## Key Points

- **Real-time output**: Text appears as the agent generates it, not after completion
- **Tool visibility**: See each tool call with its arguments as it executes
- **Progress tracking**: Know what the agent is doing at any moment
- **Interruption**: Can potentially interrupt long-running operations (implementation-dependent)
- **Same response**: Final response object has same structure as non-streaming execution
- **Fluent interface**: Combine `onText()`, `onToolUse()`, and configuration methods
- **Use cases**: Progress bars, interactive UIs, debugging, long-running tasks
