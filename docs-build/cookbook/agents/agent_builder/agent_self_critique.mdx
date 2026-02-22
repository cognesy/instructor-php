---
title: 'Agent Self-Critique Pattern'
docname: 'agent_self_critique'
order: 5
id: 'bfb1'
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
- `AgentEventConsoleObserver`: Provides visibility into continuation decisions showing SelfCritic evaluations

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\File\ListDirTool;
use Cognesy\Agents\Capability\File\SearchFilesTool;
use Cognesy\Agents\Capability\File\UseFileTools;
use Cognesy\Agents\Capability\SelfCritique\UseSelfCritique;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Messages\Messages;

// Create console logger - showContinuation reveals self-critique decisions
$logger = new AgentEventConsoleObserver(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,  // Shows SelfCritic criterion in evaluation
    showToolArgs: true,
);

// Configure working directory
$workDir = dirname(__DIR__, 3);

// Build agent with self-critique capability
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseTools(
        ListDirTool::inDirectory($workDir),
        SearchFilesTool::inDirectory($workDir),
    ))
    ->withCapability(new UseSelfCritique(
        maxIterations: 2,  // Allow up to 2 critique iterations
    ))
    ->withCapability(new UseGuards(maxSteps: 12, maxTokens: 12288, maxExecutionTime: 90))
    ->build()
    ->wiretap($logger->wiretap());

// Ask a question where the agent might give a superficial answer
$question = "What testing framework does this project use? Be specific. Provide fragments of files as evidence.";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

echo "=== Agent Execution Log ===\n";
echo "Question: {$question}\n\n";

// Execute agent until completion
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$answer = $finalState->finalResponse()->toString() ?: 'No answer';
echo "Answer: {$answer}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";

// Assertions
assert(!empty($finalState->finalResponse()->toString()), 'Expected non-empty response');
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
assert($finalState->usage()->total() > 0, 'Expected token usage > 0');
?>
```
