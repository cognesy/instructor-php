---
title: 'Pi CLI - Basic'
docname: 'pi_basic'
id: 'b4f1'
tags:
  - 'agent-ctrl'
  - 'pi-cli'
  - 'cli-agent'
---
## Overview

This example demonstrates how to use the Pi CLI integration to execute
simple prompts. The `AgentCtrl` facade provides a clean API for invoking the
`pi` CLI in JSON mode with full event observability.

Key concepts:
- `AgentCtrl::pi()`: Factory for Pi agent builder
- `ephemeral()`: Run without saving session state
- `withThinking()`: Control reasoning depth (6 levels from off to xhigh)
- `AgentCtrlConsoleLogger`: Shows execution lifecycle with color-coded labels

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;

// Create a console logger for visibility into agent execution
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showPipeline: true,  // Show request/response pipeline details
);

echo "=== Agent Execution Log ===\n\n";

$response = AgentCtrl::pi()
    ->wiretap($logger->wiretap())
    ->ephemeral()
    ->execute('What is the capital of France? Answer briefly.');

echo "\n=== Result ===\n";
if ($response->isSuccess()) {
    echo "Answer: " . $response->text() . "\n";

    if ($response->sessionId()) {
        echo "Session: {$response->sessionId()}\n";
    }
    if ($response->usage()) {
        echo "Tokens: {$response->usage()->input} in / {$response->usage()->output} out\n";
    }
    if ($response->cost()) {
        echo "Cost: $" . number_format($response->cost(), 4) . "\n";
    }
} else {
    echo "Error: Command failed with exit code {$response->exitCode}\n";
    exit(1);
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [pi] [EXEC] Execution started [prompt=What is the capital of France? Answer briefly.]
14:32:15.124 [pi] [REQT] Request built [type=PiRequest, duration=0ms]
14:32:15.125 [pi] [CMD ] Command spec created [args=4, duration=0ms]
14:32:15.126 [pi] [SBOX] Policy configured [driver=host, timeout=120s, network=on]
14:32:15.127 [pi] [SBOX] Ready [driver=host, setup=1ms]
14:32:15.128 [pi] [PROC] Process started [commands=4]
14:32:16.234 [pi] [RESP] Parsing started [format=jsonl, size=512]
14:32:16.235 [pi] [RESP] Data extracted [events=6, tools=0, text=42 chars, duration=1ms]
14:32:16.236 [pi] [RESP] Parsing completed [duration=2ms]
14:32:16.237 [pi] [DONE] Execution completed [exit=0, tools=0, cost=$0.0003, tokens=58]

=== Result ===
Answer: The capital of France is Paris.
Session: abc123-def456
Tokens: 34 in / 24 out
Cost: $0.0003
```

## Key Points

- **Simple execution**: One method call handles the entire interaction
- **Full observability**: Console logger shows request building, sandbox setup, and response parsing
- **Ephemeral mode**: Use `ephemeral()` for one-off prompts that don't need session persistence
- **Cost and usage**: Pi provides both token usage and cost data in the response
