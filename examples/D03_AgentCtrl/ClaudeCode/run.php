---
title: 'Claude Code CLI - Basic'
docname: 'claude_code_basic'
id: '6072'
---
## Overview

This example demonstrates how to use the Claude Code CLI integration to execute
simple prompts. The `AgentCtrl` facade provides a clean API for invoking the
`claude` CLI in headless mode with full event observability via `AgentCtrlConsoleLogger`.

Key concepts:
- `AgentCtrl::claudeCode()`: Factory for Claude Code agent builder
- `withMaxTurns()`: Limit agentic loop iterations
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

$response = AgentCtrl::claudeCode()
    ->wiretap($logger->wiretap())
    ->withMaxTurns(1)
    ->execute('What is the capital of France? Answer briefly.');

echo "\n=== Result ===\n";
if ($response->isSuccess()) {
    echo "Answer: " . $response->text() . "\n";

    if ($response->sessionId) {
        echo "Session: {$response->sessionId}\n";
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

14:32:15.123 [claude-code] [EXEC] Execution started [prompt=What is the capital of France? Answer briefly.]
14:32:15.124 [claude-code] [REQT] Request built [type=ClaudeRequest, duration=0ms]
14:32:15.125 [claude-code] [CMD ] Command spec created [args=8, duration=0ms]
14:32:15.126 [claude-code] [SBOX] Policy configured [driver=host, timeout=120s, network=on]
14:32:15.127 [claude-code] [SBOX] Ready [driver=host, setup=1ms]
14:32:15.128 [claude-code] [PROC] Process started [commands=8]
14:32:16.234 [claude-code] [RESP] Parsing started [format=stream-json, size=456]
14:32:16.235 [claude-code] [RESP] Data extracted [events=3, tools=0, text=42 chars, duration=1ms]
14:32:16.236 [claude-code] [RESP] Parsing completed [duration=2ms]
14:32:16.237 [claude-code] [DONE] Execution completed [exit=0, tools=0]

=== Result ===
Answer: The capital of France is Paris.
Session: session-abc123
```

## Key Points

- **Simple execution**: One method call handles the entire interaction
- **Full observability**: Console logger shows request building, sandbox setup, and response parsing
- **Pipeline visibility**: Enable `showPipeline` to see request/response internals
