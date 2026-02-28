---
title: 'Agent Planning Subagent Tool'
docname: 'agent_planning_subagent'
order: 9
id: '2c71'
---
## Overview

`UsePlanningSubagent` exposes planning as a tool (`plan_with_subagent`) that the parent
agent can call before implementation. The parent generates a task specification, the planner
subagent can use an isolated tool set, and it returns a dense markdown plan back to the
parent for execution.

This pattern provides:

- **Planner isolation**: planning runs in a separate subagent context
- **Tool scoping**: planner tools can differ from the parent tool set
- **No recursion**: planner toolset automatically removes `spawn_subagent` and `plan_with_subagent`
- **Prompt-level guidance**: capability appends instructions describing required specification sections

Key concepts:
- `UsePlanningSubagent`: installs `plan_with_subagent` and planning instructions
- `parentInstructions`: system prompt fragment telling the parent when/how to call planner
- `plannerSystemPrompt`: specialist prompt for the planning subagent
- `plannerTools`: optional allowlist of tools available to planner subagent
- `plannerBudget`: optional guard budget for the planner execution

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\File\UseFileTools;
use Cognesy\Agents\Capability\PlanningSubagent\UsePlanningSubagent;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Messages\Messages;

$logger = new AgentEventConsoleObserver(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

$workDir = dirname(__DIR__, 3);

$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UsePlanningSubagent(
        parentInstructions: <<<'PROMPT'
For tasks that involve multiple implementation steps, call `plan_with_subagent` first.

When calling `plan_with_subagent`, provide a task specification with these sections:
- Goal
- Context
- Constraints
- Expected Outcomes
- Acceptance Criteria
PROMPT,
        plannerSystemPrompt: <<<'PROMPT'
You are a planning specialist.
Create a dense markdown plan with explicit checkpoints.
Use available tools to inspect local context if needed.
Do not implement changes.
PROMPT,
        plannerTools: new NameList('read_file', 'search_files', 'list_dir'),
        plannerBudget: new ExecutionBudget(maxSteps: 4, maxTokens: 4096, maxSeconds: 45),
    ))
    ->withCapability(new UseGuards(maxSteps: 12, maxTokens: 16384, maxExecutionTime: 120))
    ->build()
    ->wiretap($logger->wiretap());

$task = <<<'TASK'
Refactor task planning capability docs in this repository.

First, create a plan.
Then execute the plan to update docs with:
1. Capability purpose
2. Configuration options
3. Example usage

Keep edits minimal and consistent with existing style.
TASK;

$state = AgentState::empty()->withMessages(
    Messages::fromString($task)
);

echo "=== Agent Execution Log ===\n";
echo "Task: Plan first, then execute\n\n";

$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$answer = $finalState->finalResponse()->toString() ?: 'No answer';
echo "Answer: {$answer}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";

assert(!empty($finalState->finalResponse()->toString()), 'Expected non-empty response');
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
assert($finalState->usage()->total() > 0, 'Expected token usage > 0');
?>
```
