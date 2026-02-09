---
title: 'Agent with Bash Tool'
docname: 'agent_loop_bash_tool'
---

## Overview

The `UseBash` capability gives the agent the ability to execute shell commands. This is
useful for system administration tasks, running scripts, or gathering system information.
The agent decides which commands to run based on the task.

Key concepts:
- `UseBash`: Capability that adds bash command execution
- `BashTool`: Executes shell commands with configurable sandboxing
- The agent autonomously decides which commands to run
- `AgentConsoleLogger`: Shows tool arguments including executed commands

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Bash\UseBash;
use Cognesy\Agents\Broadcasting\AgentConsoleLogger;
use Cognesy\Agents\Core\Data\AgentState;

$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,
);

// Build agent with bash capability
$loop = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withMaxSteps(5)
    ->build()
    ->wiretap($logger->wiretap());

$state = AgentState::empty()->withUserMessage(
    'What is the current date and time? Also show the hostname and working directory. Be concise.'
);

echo "=== Agent Execution ===\n\n";
$finalState = $loop->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
?>
```
