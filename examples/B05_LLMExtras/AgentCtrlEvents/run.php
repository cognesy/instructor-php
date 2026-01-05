---
title: 'Agent Control Events & Monitoring'
docname: 'agent_ctrl_events'
---

## Overview

AgentCtrl provides a comprehensive event system for monitoring agent execution. This enables
logging, telemetry, debugging, and custom integrations. You can either observe all events
using a wiretap, or listen to specific event types for targeted monitoring.

Key concepts:
- `wiretap()`: Observe ALL events with a single callback
- `onEvent()`: Listen to specific event types (started, completed, text received, etc.)
- Event types: `AgentExecutionStarted`, `AgentTextReceived`, `AgentToolUsed`, `AgentExecutionCompleted`, `AgentErrorOccurred`
- Real-time monitoring: Events fire during streaming execution

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentEvent;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\AgentCtrl\Event\AgentTextReceived;
use Cognesy\AgentCtrl\Event\AgentToolUsed;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;

$agent = AgentCtrl::make(AgentType::OpenCode);

// Option 1: Wiretap observes ALL events
$agent->wiretap(function(AgentEvent $event): void {
    $timestamp = $event->createdAt->format('H:i:s.v');
    echo "[{$timestamp}] EVENT: {$event->name()}\n";
});

// Option 2: Listen to specific events
$agent->onEvent(AgentExecutionStarted::class, function(AgentExecutionStarted $event): void {
    echo "-> Agent {$event->agentType->value} starting\n";
});

$agent->onEvent(AgentTextReceived::class, function(AgentTextReceived $event): void {
    $preview = strlen($event->text) > 50 ? substr($event->text, 0, 50) . '...' : $event->text;
    echo "-> Text: \"{$preview}\"\n";
});

$agent->onEvent(AgentToolUsed::class, function(AgentToolUsed $event): void {
    echo "-> Tool: {$event->tool}\n";
});

$agent->onEvent(AgentExecutionCompleted::class, function(AgentExecutionCompleted $event): void {
    echo "-> Completed: {$event->toolCallCount} tool calls, cost: $" . number_format($event->cost, 4) . "\n";
});

// Execute with streaming to see events in real-time
$response = $agent->executeStreaming('List files in current directory and explain what you see.');

if ($response->isSuccess()) {
    echo "\nFinal response:\n";
    echo $response->text() . "\n";
}
```

## Expected Output

```
[14:32:15.123] EVENT: AgentExecutionStarted
-> Agent opencode starting
[14:32:15.456] EVENT: AgentTextReceived
-> Text: "I'll list the files in the current directory..."
[14:32:16.234] EVENT: AgentToolUsed
-> Tool: bash
[14:32:16.567] EVENT: AgentTextReceived
-> Text: "The directory contains: composer.json, src/, ..."
[14:32:17.890] EVENT: AgentExecutionCompleted
-> Completed: 1 tool calls, cost: $0.0034

Final response:
The directory contains several PHP project files including composer.json for
dependencies, a src/ directory with source code, and configuration files.
```

## Key Points

- **Wiretap pattern**: Observe all events with `wiretap()` for comprehensive logging
- **Targeted listening**: Use `onEvent()` for specific event types when you only care about certain events
- **Real-time monitoring**: Events fire as execution progresses, not after completion
- **Rich metadata**: Events include timestamps, model info, token usage, costs, and tool details
- **Error handling**: Listen to `AgentErrorOccurred` for exception handling
- **Multiple listeners**: You can attach multiple callbacks to the same event type
- **Use cases**: Logging, telemetry, progress bars, debugging, analytics
