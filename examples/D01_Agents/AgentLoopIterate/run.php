---
title: 'AgentLoop::iterate()'
docname: 'agent_loop_iterate'
order: 2
id: '9ff0'
---
## Overview

`AgentLoop::iterate()` yields the agent state after each step, giving you fine-grained
control over the execution. This is useful for streaming progress, implementing custom
stop logic, or inspecting intermediate states between LLM calls.

You can combine `iterate()` with `AgentConsoleLogger` to get detailed event output
(tool calls, inference, continuation decisions) alongside your own step-by-step logic.
The logger hooks into the event system and prints as events fire during iteration.

Key concepts:
- `iterate()`: Returns an iterable that yields `AgentState` after each step
- `AgentConsoleLogger`: Attach via `wiretap()` for detailed execution logging
- Step-by-step inspection of tool calls, token usage, and agent decisions
- Early termination by breaking out of the loop

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\File\ReadFileTool;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentConsoleLogger;
use Cognesy\Messages\Messages;

$workDir = dirname(__DIR__, 3);

// AgentConsoleLogger provides detailed event output during iteration
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
);

$loop = AgentLoop::default()
    ->withTool(ReadFileTool::inDirectory($workDir))
    ->wiretap($logger->wiretap());

$state = AgentState::empty()->withMessages(
    Messages::fromString('Read the composer.json file and tell me the project name.')
);

echo "=== Stepping through agent loop ===\n\n";

// iterate() yields state after each step; the logger prints events as they fire
$stepNum = 0;
foreach ($loop->iterate($state) as $stepState) {
    $stepNum++;
    // At yield time the step has been completed, so use lastStep()
    $step = $stepState->lastStep();

    $hasToolCalls = $step?->hasToolCalls() ?? false;
    $tokens = $stepState->usage()->total();
    $status = $stepState->status()->value;

    // Custom per-step output alongside the logger's event output
    $toolsLabel = $hasToolCalls ? 'yes' : 'no';
    echo "  >> Step {$stepNum}: status={$status}, has_tools={$toolsLabel}, tokens={$tokens}\n\n";

    // Early termination example: stop after 5 steps regardless
    if ($stepNum >= 5) {
        echo "\n[Breaking early after {$stepNum} steps]\n";
        break;
    }
}

echo "\n=== Final Result ===\n";
$response = $stepState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
echo "Total steps: {$stepState->stepCount()}\n";
echo "Total tokens: {$stepState->usage()->total()}\n";
?>
```
