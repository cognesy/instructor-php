---
title: 'Agent Subagent Orchestration'
docname: 'agent_subagents'
---

## Overview

Subagents enable decomposition of complex tasks into isolated subtasks. The main agent
orchestrates multiple subagents, each with specialized roles and tools. This pattern
provides:

- **Context isolation**: Each subagent has clean context without cross-contamination
- **Parallel execution**: Multiple subagents can work simultaneously
- **Specialized capabilities**: Each subagent has specific tools for its role
- **Scalability**: Handle many independent subtasks without context overflow
- **Result aggregation**: Main agent synthesizes subagent outputs

Key concepts:
- `UseSubagents`: Capability that enables subagent spawning
- `AgentRegistry`: Registry of available subagent specifications
- `AgentSpec`: Defines subagent role, tools, and behavior
- `AgentConsoleLogger`: Shows parent/child agent IDs for tracking orchestration

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\File\UseFileTools;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\SpawnSubagentTool;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Agents\AgentTemplate\Registry\AgentRegistry;
use Cognesy\Agents\AgentTemplate\Spec\AgentSpec;
use Cognesy\Agents\Broadcasting\AgentConsoleLogger;
use Cognesy\Messages\Messages;

// Create console logger - shows agent IDs for parent/child tracking
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

// Configure working directory
$workDir = dirname(__DIR__, 3);

// Create subagent registry
$registry = new AgentRegistry();

// Register code reviewer subagent
$registry->register(new AgentSpec(
    name: 'reviewer',
    description: 'Reviews code files and identifies issues',
    systemPrompt: 'You review code files and identify issues. Read the file and provide a concise assessment focusing on code quality, potential bugs, and improvements.',
    tools: ['read_file'],
));

// Register documentation generator subagent
$registry->register(new AgentSpec(
    name: 'documenter',
    description: 'Generates documentation for code',
    systemPrompt: 'You generate documentation for code. Read the file and create brief, clear documentation explaining what the code does and how to use it.',
    tools: ['read_file'],
));

SpawnSubagentTool::clearSubagentStates();

// Build main orchestration agent
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseSubagents(provider: $registry))
    ->build()
    ->wiretap($logger->wiretap());

// Task requiring multiple isolated reviews
$task = <<<TASK
Review these three files and provide a summary:
1. packages/agents/src/AgentBuilder/AgentBuilder.php
2. packages/agents/src/Agent/Data/AgentState.php
3. packages/agents/src/Agent/Agent.php

For each file, spawn a reviewer subagent. Then summarize the findings.
TASK;

$state = AgentState::empty()->withMessages(
    Messages::fromString($task)
);

echo "=== Agent Execution Log ===\n";
echo "Task: Review multiple files using subagents\n\n";

// Execute agent until completion
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$summary = $finalState->currentStep()?->outputMessages()->toString() ?? 'No summary';
echo "Answer: {$summary}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Subagents spawned: " . count(SpawnSubagentTool::getSubagentStates()) . "\n";
echo "Tokens: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";
?>
```
