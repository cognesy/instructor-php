---
title: 'Agent Control Events & Monitoring'
docname: 'agent_ctrl_events'
id: '4074'
---
## Overview

AgentCtrl provides a comprehensive event system for monitoring agent execution. Use the
built-in `AgentCtrlConsoleLogger` for formatted output, or attach custom listeners for
targeted monitoring. Events fire in real-time during execution.

Key concepts:
- `AgentCtrlConsoleLogger`: Built-in wiretap that formats events for console output
- `wiretap()`: Observe ALL events with a single callback
- `onEvent()`: Listen to specific event types (started, completed, text received, etc.)
- Event types: `AgentExecutionStarted`, `AgentTextReceived`, `AgentToolUsed`, `AgentExecutionCompleted`, `AgentErrorOccurred`

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentToolUsed;

// 1. Built-in console logger: formatted, color-coded output
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,   // Show tool input args
    showSandbox: false,   // Hide sandbox setup events
    showPipeline: false,  // Hide request/response pipeline
    showStreaming: false,  // Hide stream events
);

$agent = AgentCtrl::make(AgentType::OpenCode)
    ->wiretap($logger->wiretap());

// 2. Targeted listeners: subscribe to specific event types
$agent->onEvent(AgentToolUsed::class, function (AgentToolUsed $event) {
    echo "\n  >>> Tool used: {$event->tool}\n\n";
});

$agent->onEvent(AgentExecutionCompleted::class, function (AgentExecutionCompleted $event) {
    echo "\n=== Execution Complete ===\n";
    echo "  Tools: {$event->toolCallCount}\n";
    if ($event->cost !== null) {
        echo "  Cost: $" . number_format($event->cost, 4) . "\n";
    }
    $tokens = ($event->inputTokens ?? 0) + ($event->outputTokens ?? 0);
    if ($tokens > 0) {
        echo "  Tokens: {$tokens}\n";
    }
});

// Run the agent
echo "=== Agent Execution Log ===\n\n";
$response = $agent->executeStreaming('List files in current directory and explain what you see.');

echo "\n=== Result ===\n";
if ($response->isSuccess()) {
    echo "Answer: " . $response->text() . "\n";
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [opencode] [EXEC] Execution started [prompt=List files in current directory...]
14:32:15.234 [opencode] [PROC] Process started [commands=1]
14:32:15.456 [opencode] [TEXT] Text received [length=48]
14:32:16.234 [opencode] [TOOL] bash {command=ls -la}

  >>> Tool used: bash

14:32:16.567 [opencode] [TEXT] Text received [length=156]
14:32:17.890 [opencode] [DONE] Execution completed [exit=0, tools=1, cost=$0.0034, tokens=198]

=== Execution Complete ===
  Tools: 1
  Cost: $0.0034
  Tokens: 198

=== Result ===
Answer: The directory contains several PHP project files including composer.json for
dependencies, a src/ directory with source code, and configuration files.
```

## Key Points

- **Console logger**: `AgentCtrlConsoleLogger` provides clean, color-coded event output with configurable toggles
- **Wiretap pattern**: Observe all events with `wiretap()` for comprehensive logging
- **Targeted listening**: Use `onEvent()` for specific event types when you only care about certain events
- **Composable**: Combine the console logger with targeted listeners
- **Real-time monitoring**: Events fire as execution progresses, not after completion
- **Rich metadata**: Events include timestamps, model info, token usage, costs, and tool details
- **Use cases**: Logging, telemetry, progress bars, debugging, analytics
