---
title: 'Basic Agent Control Usage'
docname: 'agent_ctrl_basic'
---

## Overview

AgentCtrl provides a unified interface for executing prompts against CLI-based code agents
(like Claude Code, OpenCode, Codex, etc.). This example demonstrates the simplest possible
usage: sending a prompt and receiving a structured response with metadata.

Key concepts:
- `AgentCtrl::make()`: Factory for creating agent instances
- `AgentType`: Enum specifying which CLI agent to use
- `AgentResponse`: Structured response with text, session info, usage stats, and cost

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Enum\AgentType;

// Execute a prompt against OpenCode agent
$response = AgentCtrl::make(AgentType::OpenCode)
    ->execute('Explain the SOLID principles in software design. List each principle with a one-line explanation.');

// Check if successful
if ($response->isSuccess()) {
    echo "RESPONSE:\n";
    echo $response->text() . "\n\n";

    // Access metadata
    echo "STATS:\n";
    echo "  Agent: {$response->agentType->value}\n";

    if ($response->sessionId) {
        echo "  Session: {$response->sessionId}\n";
    }
    if ($response->usage) {
        echo "  Tokens: {$response->usage->input} input, {$response->usage->output} output\n";
    }
    if ($response->cost) {
        echo "  Cost: $" . number_format($response->cost, 4) . "\n";
    }
} else {
    echo "ERROR: Request failed with exit code {$response->exitCode}\n";
}
?>
```

## Expected Output

```
RESPONSE:
The SOLID principles are:

1. Single Responsibility Principle (SRP): A class should have only one reason to change
2. Open/Closed Principle (OCP): Software entities should be open for extension but closed for modification
3. Liskov Substitution Principle (LSP): Derived classes must be substitutable for their base classes
4. Interface Segregation Principle (ISP): Clients should not be forced to depend on interfaces they don't use
5. Dependency Inversion Principle (DIP): High-level modules should not depend on low-level modules; both should depend on abstractions

STATS:
  Agent: opencode
  Session: session-abc123
  Tokens: 42 input, 156 output
  Cost: $0.0023
```

## Key Points

- **Unified interface**: Same API works across different CLI agents
- **Agent selection**: Use `AgentType` enum to specify which agent to use
- **Response metadata**: Access session IDs, token usage, and cost information
- **Error handling**: Check `isSuccess()` before accessing response data
- **Simple execution**: One method call (`execute()`) handles the entire interaction
