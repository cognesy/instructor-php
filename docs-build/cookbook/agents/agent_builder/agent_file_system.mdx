---
title: 'Agent with File System Tools'
docname: 'agent_file_system'
order: 2
id: '2b2f'
---
## Overview

Agents can be equipped with file system capabilities to read, write, search, and edit files
within a specified working directory. This enables code analysis, documentation generation,
refactoring assistance, and other file-based operations. The agent determines which file
operations to perform based on the task.

Key concepts:
- `UseFileTools`: Capability that adds core file tools (`read_file`, `write_file`, `edit_file`)
- `UseTools`: Adds extra tools explicitly when needed (`list_dir`, `search_files`)
- Working directory: Root path for all file operations (security boundary)
- Available tools: `read_file`, `write_file`, `edit_file`, `list_dir`, `search_files`
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
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentConsoleLogger;
use Cognesy\Messages\Messages;

// Create console logger for execution visibility
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

// Configure working directory (security boundary)
$workDir = dirname(__DIR__, 3);  // Project root

// Build agent with file system capabilities
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseTools(
        ListDirTool::inDirectory($workDir),
        SearchFilesTool::inDirectory($workDir),
    ))
    ->withCapability(new UseGuards(maxSteps: 8, maxTokens: 8192, maxExecutionTime: 45))
    ->build()
    ->wiretap($logger->wiretap());

// Create task that requires file access
$task = <<<TASK
Read the composer.json file and tell me:
1. What is the project name?
2. What PHP version is required?
3. List the first 5 dependencies (require section only).
Be concise.
TASK;

// Execute with initial state
$state = AgentState::empty()->withMessages(
    Messages::fromString($task)
);

echo "=== Agent Execution Log ===\n\n";

// Execute agent until completion
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$response = $finalState->finalResponse()->toString() ?: 'No response';
echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";

// Assertions
assert(!empty($finalState->finalResponse()->toString()), 'Expected non-empty response');
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
assert($finalState->usage()->total() > 0, 'Expected token usage > 0');
?>
```
