---
title: 'Basic Agent Control Usage'
docname: 'agent_ctrl_basic'
id: 'e971'
---
## Overview

AgentCtrl provides a unified interface for executing prompts against CLI-based code agents
(like Claude Code, OpenCode, Codex, etc.). This example demonstrates the simplest possible
usage: sending a prompt and receiving a structured response with metadata.

Key concepts:
- `AgentCtrl::make()`: Factory for creating agent instances
- `AgentType`: Enum specifying which CLI agent to use
- `AgentResponse`: Structured response with text, session info, usage stats, and cost
- `AgentCtrlConsoleLogger`: Provides visibility into agent execution stages

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Broadcasting\AgentCtrlConsoleLogger;
use Cognesy\AgentCtrl\Enum\AgentType;

// Create a console logger for visibility into agent execution
$logger = new AgentCtrlConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,
);

// Execute a prompt against OpenCode agent
echo "=== Agent Execution Log ===\n\n";

$response = AgentCtrl::make(AgentType::OpenCode)
    ->wiretap($logger->wiretap())
    ->execute('Explain the SOLID principles in software design. List each principle with a one-line explanation.');

echo "\n=== Result ===\n";
if ($response->isSuccess()) {
    echo "Answer: " . $response->text() . "\n";
    echo "Agent: {$response->agentType->value}\n";

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
    echo "ERROR: Request failed with exit code {$response->exitCode}\n";
}
?>
```

## Expected Output

```
=== Agent Execution Log ===

14:32:15.123 [opencode] [EXEC] Execution started [prompt=Explain the SOLID principles...]
14:32:16.456 [opencode] [DONE] Execution completed [exit=0, tools=0, tokens=198]

=== Result ===
Answer: The SOLID principles are:
1. Single Responsibility Principle (SRP): A class should have only one reason to change
2. Open/Closed Principle (OCP): Open for extension but closed for modification
3. Liskov Substitution Principle (LSP): Derived classes must be substitutable for base classes
4. Interface Segregation Principle (ISP): Don't force clients to depend on unused interfaces
5. Dependency Inversion Principle (DIP): Depend on abstractions, not concretions
Agent: opencode
Session: session-abc123
Tokens: 42 in / 156 out
Cost: $0.0023
```

## Key Points

- **Unified interface**: Same API works across different CLI agents
- **Agent selection**: Use `AgentType` enum to specify which agent to use
- **Console logger**: `AgentCtrlConsoleLogger` shows execution stages with color-coded labels
- **Response metadata**: Access session IDs, token usage, and cost information
- **Error handling**: Check `isSuccess()` before accessing response data
- **Simple execution**: One method call (`execute()`) handles the entire interaction
