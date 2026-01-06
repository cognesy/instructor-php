---
title: 'Agent with File System Tools'
docname: 'agent_file_system'
---

## Overview

Agents can be equipped with file system capabilities to read, write, search, and edit files
within a specified working directory. This enables code analysis, documentation generation,
refactoring assistance, and other file-based operations. The agent determines which file
operations to perform based on the task.

Key concepts:
- `UseFileTools`: Capability that adds file system tools to the agent
- Working directory: Root path for all file operations (security boundary)
- Available tools: `read_file`, `write_file`, `edit_file`, `list_dir`, `search_files`
- Multi-step execution: Agent reads files, analyzes content, and responds

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Messages\Messages;

// Configure working directory (security boundary)
$workDir = dirname(__DIR__, 3);  // Project root

// Build agent with file system capabilities
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->build();

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

// Run agent loop
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            echo "  → {$toolCall->name()}()\n";
        }
    }
}

// Extract final response
$response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

echo "\nAnswer:\n";
echo $response . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Status: {$state->status()->value}\n";
?>
```

## Expected Output

```
Step 1: [tool_use]
  → read_file()

Step 2: [response]

Answer:
Based on composer.json:
1. Project name: cognesy/instructor-php
2. PHP version: >=8.2
3. First 5 dependencies:
   - symfony/serializer: ^7.1
   - symfony/property-info: ^7.1
   - phpdocumentor/reflection-docblock: ^5.4
   - symfony/http-client: ^7.1
   - saloonphp/saloon: ^3.10

Stats:
  Steps: 2
  Status: finished
```

## Key Points

- **Working directory**: File tools are restricted to specified directory for security
- **Tool discovery**: Agent automatically determines which file operations to use
- **Multi-step execution**: Agent reads files in one step, analyzes in the next
- **Available operations**: Read, write, edit, list directories, search by pattern
- **Security boundary**: All file paths are validated against working directory
- **Automatic tool use**: No manual tool specification required - agent decides
- **Use cases**: Code analysis, documentation generation, refactoring, file migrations
