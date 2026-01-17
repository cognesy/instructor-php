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
- `Agent::finalStep()`: Executes the agent loop until completion


## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Core\Data\AgentState;
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
$finalState = $agent->finalStep($state);

// Extract response
$response = $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';

echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Status: {$finalState->status()->value}\n";
?>
```


## Expected Output

```
Answer: The capital of France is Paris.
Steps: 1
Status: finished
```

## Key Points

- **No tools**: This agent has no tools attached, so it only uses LLM reasoning
- **Single step**: Simple Q&A typically completes in one step
- **Immutable state**: Each step returns a new `AgentState` instance
- **Status tracking**: Final status indicates success/failure/timeout
