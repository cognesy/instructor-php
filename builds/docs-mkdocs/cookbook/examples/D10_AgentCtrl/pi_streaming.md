---
title: 'Pi CLI - Streaming'
docname: 'pi_streaming'
id: 'pi01'
tags:
  - 'agent-ctrl'
  - 'pi-cli'
  - 'streaming'
---
## Overview

This example demonstrates real-time streaming output from the Pi CLI.
Text and tool calls are displayed as they arrive, with `AgentCtrlConsoleLogger`
providing execution lifecycle visibility alongside the streaming output.

Key concepts:
- `executeStreaming()`: Execute with real-time output
- `onText()` / `onToolUse()`: Callbacks for streaming events
- `withTools()`: Control which tools the agent can use
- `AgentCtrlConsoleLogger`: Shows execution lifecycle alongside streaming

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

$response = AgentCtrl::pi()
    ->wiretap($logger->wiretap())
    ->ephemeral()
    ->withTools(['read', 'grep', 'find', 'ls'])
    ->inDirectory(getcwd())
    ->onText(function (string $text) {
        echo $text;
    })
    ->onToolUse(function (string $tool, array $input, ?string $output) use (&$toolCalls) {
        $target = $input['command'] ?? $input['file_path'] ?? $input['pattern'] ?? '';
        if (strlen($target) > 40) {
            $target = '...' . substr($target, -37);
        }
        $toolCalls[] = $tool;
        echo "\n  >> [{$tool}] {$target}\n";
    })
    ->executeStreaming('Read the first 5 lines of composer.json in this directory and describe what you see.');

echo "\n=== Result ===\n";
echo "Tools used: " . implode(' > ', $toolCalls) . "\n";
echo "Total tool calls: " . count($toolCalls) . "\n";

if ($response->usage()) {
    echo "Tokens: {$response->usage()->input} in / {$response->usage()->output} out\n";
}
if ($response->cost()) {
    echo "Cost: $" . number_format($response->cost(), 4) . "\n";
}

if (!$response->isSuccess()) {
    echo "Error: Command failed with exit code {$response->exitCode}\n";
    exit(1);
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [pi] [EXEC] Execution started [prompt=Read the first 5 lines of composer.json...]
14:32:15.234 [pi] [PROC] Process started [commands=8]
I'll read the first 5 lines of composer.json.
14:32:16.234 [pi] [TOOL] read {file_path=composer.json}

  >> [read] composer.json

The first 5 lines of composer.json show:
- The project name and description
- The license type
- Autoload configuration
14:32:17.890 [pi] [DONE] Execution completed [exit=0, tools=1, cost=$0.0005, tokens=104]

=== Result ===
Tools used: read
Total tool calls: 1
Tokens: 42 in / 62 out
Cost: $0.0005
```

## Key Points

- **Real-time output**: Text appears as the agent generates it
- **Tool visibility**: See each tool call with arguments as it executes
- **Read-only mode**: Use `withTools()` to restrict to safe, read-only tools
- **Console logger**: Execution lifecycle events interleaved with streaming output
- **Cost visibility**: Pi provides cost tracking in the response
