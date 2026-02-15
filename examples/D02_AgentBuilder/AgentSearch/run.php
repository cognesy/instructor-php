---
title: 'Agent-Driven Codebase Search'
docname: 'agent_search'
order: 6
id: '4e0c'
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
- `SearchFilesTool`: Search for files by filename/path pattern
- `ReadFileTool`: Read file contents
- `UseSubagents`: Spawn specialized subagents for subtasks
- `AgentConsoleLogger`: Provides visibility into agent execution stages

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
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentConsoleLogger;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Messages\Messages;

// Create console logger for execution visibility
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,  // Show search patterns and file paths
);

// Configure working directory
$workDir = dirname(__DIR__, 3);

// Register specialized subagents
$registry = new AgentDefinitionRegistry();

$registry->register(new AgentDefinition(
    name: 'reader',
    description: 'Reads files and extracts relevant information',
    systemPrompt: 'You read files and extract relevant information. Be thorough and precise.',
    tools: NameList::fromArray(['read_file']),
));

$registry->register(new AgentDefinition(
    name: 'searcher',
    description: 'Searches for files by filename/path patterns',
    systemPrompt: 'You search for files by filename/path patterns. Use glob patterns effectively.',
    tools: NameList::fromArray(['search_files']),
));

// Build main orchestration agent
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseTools(
        ListDirTool::inDirectory($workDir),
        SearchFilesTool::inDirectory($workDir),
    ))
    ->withCapability(new UseSubagents(provider: $registry))
    ->withCapability(new UseGuards(maxSteps: 12, maxTokens: 12288, maxExecutionTime: 90))
    ->build()
    ->wiretap($logger->wiretap());

// Ask a question that requires search
$question = "Find capability test files (e.g. *CapabilityTest.php under packages/agents/tests) and summarize what each one verifies.";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

echo "=== Agent Execution Log ===\n\n";

// Execute agent until completion
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$answer = $finalState->finalResponse()->toString() ?: 'No answer';
echo "Answer: {$answer}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";
?>
```
