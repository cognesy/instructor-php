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
- `spawn_subagent`: Tool to create and execute subagent
- Context isolation: Subagents don't see each other's work

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Agents\AgentSpec;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Capabilities\Subagent\SubagentPolicy;
use Cognesy\Messages\Messages;

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

// Build main orchestration agent
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

// Task requiring multiple isolated reviews
$task = <<<TASK
Review these three files and provide a summary:
1. src/Agent/AgentBuilder.php
2. src/Agent/AgentState.php
3. src/Agent/Agent.php

For each file, spawn a reviewer subagent. Then summarize the findings.
TASK;

$state = AgentState::empty()->withMessages(
    Messages::fromString($task)
);

// Execute with subagent spawning
echo "Task: Review multiple files\n\n";

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            if ($toolCall->name() === 'spawn_subagent') {
                $args = $toolCall->args();
                echo "  → spawn_subagent(subagent={$args['subagent']}, prompt=...)\n";
            } else {
                echo "  → {$toolCall->name()}()\n";
            }
        }
    }
}

// Extract summary
$summary = $state->currentStep()?->outputMessages()->toString() ?? 'No summary';

echo "\nSummary:\n";
echo $summary . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Subagents spawned: " . ($state->metadata()['subagent_count'] ?? 0) . "\n";
echo "  Status: {$state->status()->value}\n";
```

## Expected Output

```
Task: Review multiple files

Step 1: [tool_use]
  → spawn_subagent(subagent=reviewer, prompt=Review AgentBuilder.php)

Step 2: [tool_use]
  → spawn_subagent(subagent=reviewer, prompt=Review AgentState.php)

Step 3: [tool_use]
  → spawn_subagent(subagent=reviewer, prompt=Review Agent.php)

Step 4: [response]

Summary:
Code Review Summary:

1. AgentBuilder.php
   - Well-structured builder pattern
   - Good use of fluent interface
   - Consider adding validation for required fields

2. AgentState.php
   - Immutable state design is excellent
   - Clear separation of concerns
   - Methods are well-named and focused

3. Agent.php
   - Core agent loop is clean
   - Good error handling
   - Consider extracting step execution to separate class

Overall: Code quality is high with clear architectural patterns.

Stats:
  Steps: 4
  Subagents spawned: 3
  Status: finished
```

## Key Points

- **Context isolation**: Each subagent reviews independently without seeing other reviews
- **Scalability**: Main agent context stays clean even with many subagent calls
- **Specialized roles**: Each subagent has specific tools and instructions
- **Result aggregation**: Main agent synthesizes all subagent outputs
- **Parallel potential**: Subagents can execute concurrently (implementation-dependent)
- **AgentRegistry**: Central registry of available subagent types
- **Policy control**: SubagentPolicy defines spawning limits and behavior
- **Clean architecture**: Separation between orchestration and execution
- **Use cases**: Code review batches, multi-document analysis, parallel research, task decomposition
- **Metadata tracking**: Track subagent spawns and execution statistics
