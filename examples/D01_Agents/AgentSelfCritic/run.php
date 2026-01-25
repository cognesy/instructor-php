---
title: 'Agent Self-Critique Pattern'
docname: 'agent_self_critique'
---

## Overview

Self-critique enables agents to evaluate their own outputs and request revisions when
answers are incomplete or incorrect. This pattern uses a critic subagent that reviews
each final response and decides whether it meets quality standards or needs refinement.

This significantly improves accuracy by:
- Catching incomplete answers
- Detecting logical errors
- Ensuring answers match the original question
- Forcing deeper investigation when initial responses are superficial

Key concepts:
- `UseSelfCritique`: Capability that adds self-evaluation after each response
- `maxIterations`: Maximum number of critique-revision cycles (default: 2)
- `verbose`: Enable/disable critique feedback output (default: true)
- Revision loop: Agent revises answers based on critic feedback
- State processor: Evaluates responses and requests revisions when needed

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\File\UseFileTools;
use Cognesy\Agents\AgentBuilder\Capabilities\SelfCritique\UseSelfCritique;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

// Configure working directory
$workDir = dirname(__DIR__, 3);

// Build agent with self-critique capability
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseSelfCritique(
        maxIterations: 2,  // Allow up to 2 critique iterations
        verbose: true  // Show critique feedback in output
    ))
    ->build();

// Ask a question where the agent might give a superficial answer
$question = "What testing framework does this project use? Be specific. Provide fragments of files as evidence.";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

// Execute agent loop with self-critique
echo "Question: {$question}\n\n";

foreach ($agent->iterate($state) as $state) {
    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            echo "  → {$toolCall->name()}({$toolCall->argsAsJson()})\n";
        }
    }
}

// Extract final answer
$answer = $state->currentStep()?->outputMessages()->toString() ?? 'No answer';

echo "\nFinal Answer:\n";
echo $answer . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Status: {$state->status()->value}\n";
?>
```
