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

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\SelfCritique\UseSelfCritique;
use Cognesy\Addons\Agent\Data\AgentState;
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
$question = "What testing framework does this project use? Be specific.";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

// Execute agent loop with self-critique
echo "Question: {$question}\n\n";

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    $step = $state->currentStep();
    $stepType = $step->stepType()->value;

    echo "Step {$state->stepCount()}: [{$stepType}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            echo "  → {$toolCall->name()}()\n";
        }
    }

    // Show critic feedback
    if ($stepType === 'critique') {
        $critique = $step->metadata()['critique'] ?? '';
        if ($critique) {
            echo "  Critic: {$critique}\n";
        }
    }
}

// Extract final answer
$answer = $state->currentStep()?->outputMessages()->toString() ?? 'No answer';

echo "\nFinal Answer:\n";
echo $answer . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Revisions: " . ($state->metadata()['revision_count'] ?? 0) . "\n";
echo "  Status: {$state->status()->value}\n";
?>
```

## Expected Output

```
Question: What testing framework does this project use? Be specific.

Step 1: [response]
  Initial answer: The project likely uses PHPUnit based on PHP conventions.

Step 2: [critique]
  Critic: Answer is speculative. Search for actual test framework configuration.

Step 3: [tool_use]
  → search_files()

Step 4: [tool_use]
  → read_file()

Step 5: [response]
  Revised answer: The project uses Pest, configured in phpunit.xml and composer.json.

Step 6: [critique]
  Critic: Answer is thorough and evidence-based. Accepted.

Final Answer:
The project uses Pest as its testing framework. This is confirmed by:
1. phpunit.xml shows Pest configuration
2. composer.json requires pestphp/pest
3. Test files use Pest's describe/it syntax

Stats:
  Steps: 6
  Revisions: 1
  Status: finished
```

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
