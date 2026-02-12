---
title: 'Multi-Execution Conversations'
docname: 'agent_loop_multi_execution'
order: 9
id: 'db3d'
---
## Overview

An agent can handle multiple rounds of execution, where each round builds on the previous
conversation history. Use `forNextExecution()` to reset execution state while preserving
the full message context, then add a new user message and run `execute()` again.

This enables multi-turn interactions where the agent reasons over past tool results
to answer follow-up questions without re-executing tools.

Key concepts:
- `forNextExecution()`: Resets execution state, preserves conversation context
- `withUserMessage()`: Appends a follow-up user message to the existing conversation
- The agent sees all prior messages including tool calls and results from previous executions
- Follow-up questions can reference data gathered in earlier rounds

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\File\UseFileTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Events\AgentConsoleLogger;

$workDir = dirname(__DIR__, 3);

$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

// Build an agent with file reading capability
$loop = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withMaxSteps(5)
    ->build()
    ->wiretap($logger->wiretap());

// === Execution 1: Ask the agent to read composer.json ===
$query1 = 'Read the composer.json file and tell me the project name and its PHP version requirement.';
echo "=== Execution 1 ===\n";
echo "Query: {$query1}\n\n";

$state = AgentState::empty()->withUserMessage($query1);
$state = $loop->execute($state);

$response1 = $state->finalResponse()->toString() ?: 'No response';
echo "\nResponse: {$response1}\n\n";

// === Execution 2: Follow-up question using context from Execution 1 ===
$query2 = 'Based on what you read, does the project use PSR-4 autoloading? What are the namespace prefixes?';
echo "=== Execution 2 ===\n";
echo "Query: {$query2}\n\n";

// forNextExecution() resets execution state but keeps the full conversation history
$state = $state->forNextExecution()->withUserMessage($query2);
$state = $loop->execute($state);

$response2 = $state->finalResponse()->toString() ?: 'No response';
echo "\nResponse: {$response2}\n\n";

// === Execution 3: Another follow-up — agent reasons without tools ===
$query3 = 'Given what you know about this project, what type of project is it — a library, framework, or application? Explain briefly.';
echo "=== Execution 3 ===\n";
echo "Query: {$query3}\n\n";

$state = $state->forNextExecution()->withUserMessage($query3);
$state = $loop->execute($state);

$response3 = $state->finalResponse()->toString() ?: 'No response';
echo "\nResponse: {$response3}\n";
?>
```
