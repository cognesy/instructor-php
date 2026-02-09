---
title: 'Basic Agent Usage'
docname: 'agent_basic'
---

## Overview

The simplest use of an Agent - a straightforward Q&A without tools. The agent uses
the LLM directly to answer questions. This demonstrates the core agent loop: receiving
a message, processing it through the LLM, and returning a response.

Key concepts:
- `AgentBuilder`: Constructs configured agent instances
- `AgentState`: Immutable state container for messages and metadata
- `Agent::execute()`: Executes the agent loop until completion
- `AgentConsoleLogger`: Provides visibility into agent execution stages


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Broadcasting\AgentConsoleLogger;
use Cognesy\Messages\Messages;

// Create a console logger for visibility into agent execution
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
);

// Build a basic agent
$agent = AgentBuilder::new()
    ->withLlmPreset('anthropic')
    ->build()
    ->wiretap($logger->wiretap());

// Create initial state with user question
$state = AgentState::empty()->withMessages(
    Messages::fromString('What is the capital of France? Answer in one sentence.')
);

echo "=== Agent Execution Log ===\n\n";

// Execute agent until completion
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";
?>
```
