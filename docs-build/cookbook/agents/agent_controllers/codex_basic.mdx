---
title: 'OpenAI Codex CLI - Basic'
docname: 'codex_basic'
id: 'daea'
---
## Overview

This example demonstrates how to use the OpenAI Codex CLI integration to execute
simple prompts. The `AgentCtrl` facade provides a clean API for invoking the
`codex exec` command with full event observability.

Key concepts:
- `AgentCtrl::codex()`: Factory for Codex agent builder
- `withSandbox()`: Configure sandbox mode for file/network access
- `AgentCtrlConsoleLogger`: Shows execution lifecycle with color-coded labels

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;

// Create a console logger for visibility into agent execution
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showPipeline: true,  // Show request/response pipeline details
);

echo "=== Agent Execution Log ===\n\n";

$response = AgentCtrl::codex()
    ->wiretap($logger->wiretap())
    ->withSandbox(SandboxMode::ReadOnly)
    ->execute('What is the capital of France? Answer briefly.');

echo "\n=== Result ===\n";
if ($response->isSuccess()) {
    echo "Answer: " . $response->text() . "\n";

    if ($response->sessionId) {
        echo "Thread ID: {$response->sessionId}\n";
    }
    if ($response->usage) {
        echo "Tokens: {$response->usage->input} in / {$response->usage->output} out\n";
    }
} else {
    echo "Error: Command failed with exit code {$response->exitCode}\n";
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [codex] [EXEC] Execution started [prompt=What is the capital of France? Answer briefly.]
14:32:15.124 [codex] [REQT] Request built [type=CodexRequest, duration=0ms]
14:32:15.125 [codex] [CMD ] Command spec created [args=6, duration=0ms]
14:32:15.126 [codex] [SBOX] Policy configured [driver=host, timeout=120s, network=on]
14:32:15.127 [codex] [SBOX] Ready [driver=host, setup=1ms]
14:32:15.128 [codex] [PROC] Process started [commands=6]
14:32:16.234 [codex] [RESP] Parsing started [format=json, size=312]
14:32:16.235 [codex] [RESP] Data extracted [events=2, tools=0, text=32 chars, duration=1ms]
14:32:16.236 [codex] [RESP] Parsing completed [duration=2ms, session=thread-abc123]
14:32:16.237 [codex] [DONE] Execution completed [exit=0, tools=0, tokens=62]

=== Result ===
Answer: The capital of France is Paris.
Thread ID: thread-abc123
Tokens: 38 in / 24 out
```

## Key Points

- **Simple execution**: One method call handles the entire interaction
- **Full observability**: Console logger shows request building, sandbox, and response parsing
- **Sandbox control**: Use `withSandbox()` for file/network access control
