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
\<\?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Addons\Agent\Registry\AgentSpec;
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
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

// Ask a question that requires search
$question = "Find all test files related to Agent capabilities and tell me what they test";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

// Execute agent loop
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

// Extract answer
$answer = $state->currentStep()?->outputMessages()->toString() ?? 'No answer';

echo "\nAnswer:\n";
echo $answer . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Status: {$state->status()->value}\n";
?>
```

## Expected Output

```
Step 1: [tool_use]
  → spawn_subagent()
Step 2: [tool_use]
  → spawn_subagent()
Step 3: [response]
Answer:
I found several test files related to Agent capabilities:
1. Feature/Capabilities/BashCapabilityTest.php
   - Tests bash command execution
   - Tests command output capture
   - Tests security policies
2. Feature/Capabilities/FileCapabilityTest.php
   - Tests file reading and writing
   - Tests directory listing
   - Tests file search functionality
3. Feature/Capabilities/SubagentCapabilityTest.php
   - Tests subagent spawning
   - Tests communication between agents
   - Tests subagent lifecycle
4. Feature/Capabilities/TasksCapabilityTest.php
   - Tests task planning and tracking
   - Tests task status management
   - Tests task completion detection
Stats:
  Steps: 3
  Status: finished
```

## Key Points

- **Autonomous search**: Agent determines which files to search for and read
- **Subagent orchestration**: Main agent spawns specialized searcher and reader subagents
- **AgentRegistry**: Registry of available subagent specifications
- **Specialized tools**: Each subagent has specific tools (search, read)
- **Multi-step reasoning**: Agent synthesizes information from multiple file reads
- **Dynamic strategy**: Agent adapts search based on initial findings
- **Tool chaining**: Search results inform which files to read
- **Use cases**: Code documentation, architecture analysis, dependency mapping, test coverage reports
