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


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Messages\Messages;

// Build a basic agent
$agent = AgentBuilder::new()
    ->withLlmPreset('anthropic')  // Optional: specify LLM
    ->build();

// Create initial state with user question
$state = AgentState::empty()->withMessages(
    Messages::fromString('What is the capital of France? Answer in one sentence.')
);

// Execute agent until completion
$finalState = $agent->execute($state);

// Extract response
$response = $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';

echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Status: {$finalState->status()->value}\n";

if ($finalState->status() === AgentStatus::Failed) {
    $debug = $finalState->debug();
    $stepType = $finalState->currentStep()?->stepType()?->value;
    if ($stepType !== null) {
        echo "Step type: {$stepType}\n";
    }
    if (($debug['stopReason'] ?? '') !== '') {
        echo "Stop reason: {$debug['stopReason']}\n";
    }
    if (($debug['resolvedBy'] ?? '') !== '') {
        echo "Resolved by: {$debug['resolvedBy']}\n";
    }
    if (($debug['errors'] ?? '') !== '') {
        echo "Errors: {$debug['errors']}\n";
    }
}
?>
```
