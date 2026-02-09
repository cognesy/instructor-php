---
title: 'AgentLoop::iterate()'
docname: 'agent_loop_iterate'
---

## Overview

`AgentLoop::iterate()` yields the agent state after each step, giving you fine-grained
control over the execution. This is useful for streaming progress, implementing custom
stop logic, or inspecting intermediate states between LLM calls.

Key concepts:
- `iterate()`: Returns an iterable that yields `AgentState` after each step
- Step-by-step inspection of tool calls, token usage, and agent decisions
- Early termination by breaking out of the loop

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\File\UseFileTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;

$workDir = dirname(__DIR__, 3);

$loop = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withMaxSteps(10)
    ->build();

$state = AgentState::empty()->withMessages(
    Messages::fromString('Read the composer.json file and tell me the project name.')
);

echo "=== Stepping through agent loop ===\n\n";

$stepNum = 0;
foreach ($loop->iterate($state) as $stepState) {
    $stepNum++;
    $step = $stepState->currentStep();

    $hasToolCalls = $step?->hasToolCalls() ?? false;
    $tokens = $stepState->usage()->total();
    $status = $stepState->status()->value;

    echo "Step {$stepNum}: status={$status}, has_tools={$hasToolCalls}, tokens={$tokens}\n";

    // You can inspect tool calls made in this step
    if ($step !== null) {
        foreach ($step->toolExecutions() as $exec) {
            echo "  Tool: {$exec->name()}\n";
        }
    }

    // Early termination example: stop after 5 steps regardless
    if ($stepNum >= 5) {
        echo "\n[Breaking early after {$stepNum} steps]\n";
        break;
    }
}

echo "\n=== Final Result ===\n";
$response = $stepState->currentStep()?->outputMessages()->toString() ?? 'No response';
echo "Answer: {$response}\n";
echo "Total steps: {$stepState->stepCount()}\n";
echo "Total tokens: {$stepState->usage()->total()}\n";
?>
```
