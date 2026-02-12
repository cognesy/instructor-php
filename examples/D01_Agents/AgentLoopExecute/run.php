---
title: 'AgentLoop::execute()'
docname: 'agent_loop_execute'
order: 1
id: '2df1'
---
## Overview

The simplest way to run an agent: `AgentLoop::execute()` runs the loop to completion and
returns the final `AgentState`. The loop sends messages to the LLM, processes any tool calls,
and repeats until the LLM produces a final response with no pending tool calls.

Key concepts:
- `AgentLoop::default()`: Creates a minimal agent loop with sensible defaults
- `AgentState::empty()`: Creates an empty immutable state container
- `execute()`: Runs the full loop and returns the final state
- `AgentConsoleLogger`: Attach via `wiretap()` to see execution lifecycle events
- `finalResponse()`: Access the agent's final text output

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Broadcasting\AgentConsoleLogger;
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;

// AgentConsoleLogger shows execution lifecycle events on the console
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
);

// Create a default agent loop (no tools, just LLM conversation)
$loop = AgentLoop::default()
    ->wiretap($logger->wiretap());

// Prepare initial state with a user message
$state = AgentState::empty()->withMessages(
    Messages::fromString('What are the three primary colors? Answer in one sentence.')
);

// Execute the loop to completion
echo "=== Agent Execution ===\n\n";
$finalState = $loop->execute($state);

// Read the result
echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens used: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";
?>
```
