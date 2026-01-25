---
title: 'Agent-Driven Codebase Search'
docname: 'agent_search'
---

## Overview

Demonstrates how agents can autonomously search codebases by:
- Searching for files matching patterns
- Reading relevant files
- Synthesizing information into answers
- Using subagents for specialized tasks

This example shows the agent determining search strategy, executing searches, and
analyzing results without predefined workflows. The agent decides which files to read
based on search results.

Key concepts:
- `SearchFilesTool`: Search for files by pattern or content
- `ReadFileTool`: Read file contents
- `UseSubagents`: Spawn specialized subagents for subtasks
- AgentRegistry: Registry of available subagent specifications
- Autonomous search: Agent determines strategy based on question

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Agents\AgentTemplate\Registry\AgentRegistry;
use Cognesy\Agents\AgentTemplate\Spec\AgentSpec;
use Cognesy\Messages\Messages;

// Configure working directory
$workDir = dirname(__DIR__, 3);

// Register specialized subagents
$registry = new AgentRegistry();

$registry->register(new AgentSpec(
    name: 'reader',
    description: 'Reads files and extracts relevant information',
    systemPrompt: 'You read files and extract relevant information. Be thorough and precise.',
    tools: ['read_file'],
));

$registry->register(new AgentSpec(
    name: 'searcher',
    description: 'Searches for files matching patterns',
    systemPrompt: 'You search for files matching patterns. Use glob patterns effectively.',
    tools: ['search_files'],
));

// Build main orchestration agent
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(provider: $registry))
    ->build();

// Ask a question that requires search
$question = "Find all test files related to Agent capabilities and tell me what they test";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

// Execute agent loop
foreach ($agent->iterate($state) as $state) {
    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            echo "  → {$toolCall->name()}()\n";
        }
    }
}

// Extract answer
$answer = $state->currentStep()?->outputMessages()->toString() ?? 'No answer';

echo "\nAnswer:\n";
echo $answer . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Status: {$state->status()->value}\n";
?>
```
