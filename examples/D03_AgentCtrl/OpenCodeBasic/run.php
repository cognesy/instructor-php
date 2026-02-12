---
title: 'OpenCode CLI - Basic'
docname: 'opencode_basic'
id: 'd828'
---
## Overview

This example demonstrates how to use the OpenCode CLI integration to execute
simple prompts. The `AgentCtrl` facade provides a clean API for invoking the
`opencode run` command with full event observability.

Key concepts:
- `AgentCtrl::openCode()`: Factory for OpenCode agent builder
- `AgentCtrlConsoleLogger`: Shows execution lifecycle with color-coded labels
- `AgentResponse`: Structured response with text, session info, usage stats, and cost

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

$response = AgentCtrl::openCode()
    ->wiretap($logger->wiretap())
    ->execute('What is the capital of France? Answer briefly.');

echo "\n=== Result ===\n";
if ($response->isSuccess()) {
    echo "Answer: " . $response->text() . "\n";

    if ($response->sessionId) {
        echo "Session ID: {$response->sessionId}\n";
    }
    if ($response->usage) {
        echo "Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
    }
    if ($response->cost) {
        echo "Cost: $" . number_format($response->cost, 4) . "\n";
    }
} else {
    echo "Error: Command failed with exit code {$response->exitCode}\n";
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [opencode] [EXEC] Execution started [prompt=What is the capital of France? Answer briefly.]
14:32:15.124 [opencode] [REQT] Request built [type=OpenCodeRequest, duration=0ms]
14:32:15.125 [opencode] [CMD ] Command spec created [args=5, duration=0ms]
14:32:15.126 [opencode] [SBOX] Policy configured [driver=host, timeout=120s, network=on]
14:32:15.127 [opencode] [SBOX] Ready [driver=host, setup=1ms]
14:32:15.128 [opencode] [PROC] Process started [commands=5]
14:32:16.234 [opencode] [RESP] Parsing started [format=json, size=289]
14:32:16.235 [opencode] [RESP] Data extracted [events=2, tools=0, text=32 chars, duration=1ms]
14:32:16.236 [opencode] [RESP] Parsing completed [duration=2ms, session=session-abc123]
14:32:16.237 [opencode] [DONE] Execution completed [exit=0, tools=0, cost=$0.0008, tokens=62]

=== Result ===
Answer: The capital of France is Paris.
Session ID: session-abc123
Tokens: 38 in / 24 out
Cost: $0.0008
```

## Key Points

- **Simple execution**: One method call handles the entire interaction
- **Full observability**: Console logger shows request building, sandbox, and response parsing
- **Cost tracking**: OpenCode exposes cost information in the response
