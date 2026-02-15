---
title: 'Agent with Bash Tool'
docname: 'agent_loop_bash_tool'
order: 3
id: '06e7'
---
## Overview

Attaching `BashTool` directly to `AgentLoop` gives the agent the ability to execute shell commands. This is
useful for system administration tasks, running scripts, or gathering system information.
The agent decides which commands to run based on the task.

Key concepts:
- `AgentLoop::withTool()`: Adds a tool directly to the loop
- `BashTool`: Executes shell commands with configurable sandboxing
- The agent autonomously decides which commands to run
- `AgentConsoleLogger`: Shows tool arguments including executed commands

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Bash\BashTool;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentConsoleLogger;

$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showToolArgs: true,
);

// Build loop with BashTool directly
$loop = AgentLoop::default()
    ->withTool(BashTool::inDirectory(getcwd()))
    ->wiretap($logger->wiretap());

$state = AgentState::empty()->withUserMessage(
    'What is the current date and time? Also show the hostname and working directory. Be concise.'
);

echo "=== Agent Execution ===\n\n";
$finalState = $loop->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?? 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
?>
```
