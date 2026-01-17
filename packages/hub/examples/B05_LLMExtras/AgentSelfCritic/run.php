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

## Key Points

- **Quality enforcement**: Critic rejects superficial or incorrect answers
- **Automatic revision**: Agent revises responses based on critic feedback
- **Evidence-based**: Critic encourages fact-checking over speculation
- **Iteration limits**: Prevent infinite loops with `maxIterations` setting
- **Verbose mode**: Enable `verbose` to see critique feedback in real-time
- **Metadata tracking**: Track critique iterations and feedback
- **Critic as processor**: Uses state processor to evaluate responses
- **Continuation criteria**: Adds criteria to continue loop based on critique
- **Use cases**: Research tasks, fact-checking, technical analysis, quality-sensitive outputs
- **Trade-offs**: Higher accuracy at cost of more LLM calls and longer execution time

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\SelfCritique\UseSelfCritique;
use Cognesy\Addons\Agent\Core\Data\AgentState;
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

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

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