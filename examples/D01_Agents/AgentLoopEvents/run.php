---
title: 'Agent Events and Wiretap'
docname: 'agent_loop_events'
order: 8
id: '58c9'
---
## Overview

The agent emits events throughout its lifecycle. Events are read-only observations —
they cannot modify agent behavior (use hooks for that).

Two ways to observe events:
- `wiretap(callable)`: Receives **every** event — `AgentConsoleLogger` uses this internally
- `onEvent(EventClass, callable)`: Subscribes to a **specific** event type for custom logic

Both can be used together. The logger provides general visibility while `onEvent()` lets
you collect metrics, trigger side effects, or react to specific events.

Key concepts:
- `AgentConsoleLogger`: Built-in wiretap that formats all events for console output
- `onEvent()`: Targeted listener for a single event class
- Events include: `AgentStepCompleted`, `ToolCallStarted`, `ToolCallCompleted`,
  `InferenceResponseReceived`, `AgentExecutionCompleted`, `ContinuationEvaluated`, and more

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Bash\UseBash;
use Cognesy\Agents\Broadcasting\AgentConsoleLogger;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\InferenceResponseReceived;

// AgentConsoleLogger uses wiretap() internally to show all lifecycle events
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withMaxSteps(5)
    ->build()
    ->wiretap($logger->wiretap());

// onEvent(): subscribe to specific event types for custom logic
// This runs alongside the logger — use it to collect metrics, trigger
// side effects, or react to specific events the logger doesn't cover.

$totalInferenceMs = 0;

$agent->onEvent(InferenceResponseReceived::class, function (InferenceResponseReceived $event) use (&$totalInferenceMs) {
    $ms = $event->receivedAt->getTimestamp() * 1000 + (int)($event->receivedAt->format('u') / 1000)
        - $event->requestStartedAt->getTimestamp() * 1000 - (int)($event->requestStartedAt->format('u') / 1000);
    $totalInferenceMs += $ms;
});

$agent->onEvent(AgentExecutionCompleted::class, function (AgentExecutionCompleted $event) use (&$totalInferenceMs) {
    echo "\n  [custom] Execution summary:\n";
    echo "    Steps: {$event->totalSteps}\n";
    echo "    Total tokens: {$event->totalUsage->total()}\n";
    echo "    LLM time: {$totalInferenceMs}ms\n";
});

// Run the agent
$state = AgentState::empty()->withUserMessage(
    'What is today\'s date? Use bash to find out. Be concise.'
);

echo "=== Agent Events Demo ===\n\n";
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
?>
```
