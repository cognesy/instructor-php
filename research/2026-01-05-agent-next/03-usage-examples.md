# Agent System Usage Examples

**Date**: 2026-01-05
**Purpose**: Concrete before/after examples showing how to use enhanced agent system

## Overview

This document provides practical examples comparing current usage patterns with enhanced patterns after implementing the architectural improvements.

---

## Example 1: Codebase Exploration

### Current Approach (Explicit Agent Selection)

```php
<?php
use Cognesy\Addons\AgentBuilder\AgentBuilder;use Cognesy\Addons\AgentBuilder\Capabilities\File\UseFileTools;use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\UseSubagents;use Cognesy\Addons\Agent\Core\Data\AgentState;use Cognesy\Addons\AgentTemplate\Registry\AgentRegistry;use Cognesy\Addons\AgentTemplate\Registry\AgentSpec;use Cognesy\Messages\Messages;

// Define explorer agent
$registry = new AgentRegistry();
$registry->register(new AgentSpec(
    name: 'explorer',
    description: 'Explores codebase to find files and code',
    systemPrompt: <<<PROMPT
You are a codebase exploration specialist.
Search for files and code patterns using available tools.
PROMPT,
    tools: ['read_file', 'search_files'],
));

// Build main agent
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools(__DIR__))
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

// User question
$question = "Where are the API routes defined?";
$state = AgentState::empty()->withMessages(Messages::fromString($question));

// Execute - main agent has to manually decide to use explorer
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
}
```

**Problem**: Main agent doesn't automatically know explorer exists or when to use it.

### Enhanced Approach (Auto-Discovery + Suggestion)

```php
<?php
use Cognesy\Addons\AgentBuilder\AgentBuilder;use Cognesy\Addons\AgentBuilder\Capabilities\File\UseFileTools;use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\UseSubagents;use Cognesy\Addons\AgentBuilder\Capabilities\Suggestion\UseAgentSuggestions;use Cognesy\Addons\Agent\Core\Data\AgentState;use Cognesy\Addons\AgentTemplate\Registry\AgentRegistry;use Cognesy\Messages\Messages;

// Load agents from markdown definitions
$registry = new AgentRegistry();
$registry->loadFromDirectory(__DIR__ . '/agents');  // Loads explorer.md, planner.md, etc.

// Build main agent with discovery + suggestions
$agent = AgentBuilder::base()
    ->withSystemPrompt('You are a software development assistant.')
    ->withCapability(new UseFileTools(__DIR__))
    ->withCapability(new UseSubagents(registry: $registry))
    ->withCapability(new UseAgentSuggestions(registry: $registry))  // NEW: Auto-suggests agents
    ->withAgentDiscovery($registry)  // NEW: Adds agent list to prompt
    ->build();

// User question
$question = "Where are the API routes defined?";
$state = AgentState::empty()->withMessages(Messages::fromString($question));

// Execute
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    // Check for suggestion
    $suggested = $state->metadata()->get('suggested_agent');
    if ($suggested) {
        echo "💡 Pattern detected → suggesting '{$suggested}' agent\n";
    }

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";
}

// Main agent automatically:
// 1. UseAgentSuggestions detects "where are" pattern → suggests 'explorer'
// 2. Main agent sees all available agents in prompt
// 3. Main agent LLM decides to delegate to explorer based on task match
```

**Benefit**: Main agent automatically discovers and uses appropriate subagent.

---

## Example 2: Code Review Workflow

### Current Approach

```php
<?php
// Manual setup for code review
$registry = new AgentRegistry();
$registry->register(new AgentSpec(
    name: 'reviewer',
    description: 'Reviews code for quality issues',
    systemPrompt: 'You review code and find bugs.',
    tools: ['read_file', 'search_files'],
));

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

$question = "Review the UserController class for bugs";
$state = AgentState::empty()->withMessages(Messages::fromString($question));

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
}
```

**Problem**: No constraints prevent reviewer from modifying files it's reviewing.

### Enhanced Approach (With Constraints)

```php
<?php
use Cognesy\Addons\AgentTemplate\Registry\AgentSpec;

// Define reviewer with explicit constraints
$registry = new AgentRegistry();
$registry->register(new AgentSpec(
    name: 'reviewer',
    description: 'Reviews code for quality, bugs, and security issues',
    whenToUse: 'Use when user asks to review, analyze, or check code quality',
    systemPrompt: <<<PROMPT
You are a code review specialist. Analyze code for:
- Logic errors and bugs
- Security vulnerabilities
- Code quality issues
- Performance problems

Provide detailed findings with severity levels.
PROMPT,
    tools: ['read_file', 'search_files', 'grep'],
    prohibitedTools: ['write_file', 'edit_file'],  // NEW: Cannot modify code
    capabilities: ['code-analysis', 'bug-detection', 'security-analysis'],
    constraints: ['read-only'],  // NEW: Explicit constraint
));

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->withAgentDiscovery($registry)
    ->build();

$question = "Review the UserController class for security issues";
$state = AgentState::empty()->withMessages(Messages::fromString($question));

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
    // If reviewer tries to use write_file → AgentException thrown
}
```

**Benefit**:
- Reviewer's system prompt explicitly states READ-ONLY constraints
- UseSubagents validates tool calls against prohibitedTools
- Attempting to modify files throws exception immediately

---

## Example 3: Planning + Implementation Workflow

### Current Approach

```php
<?php
// Two separate manual agent invocations
$registry = new AgentRegistry();

// Planner agent
$registry->register(new AgentSpec(
    name: 'planner',
    description: 'Creates implementation plans',
    systemPrompt: 'You create detailed implementation plans.',
    tools: ['read_file'],
));

// Implementer agent
$registry->register(new AgentSpec(
    name: 'implementer',
    description: 'Implements code changes',
    systemPrompt: 'You implement code changes.',
    tools: ['read_file', 'write_file', 'edit_file'],
));

// Step 1: Run planner manually
$plannerAgent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

$state1 = AgentState::empty()->withMessages(
    Messages::fromString("Create plan for adding OAuth")
);
while ($plannerAgent->hasNextStep($state1)) {
    $state1 = $plannerAgent->nextStep($state1);
}
$plan = $state1->currentStep()->outputMessages()->toString();

// Step 2: Run implementer manually
$implementerAgent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

$state2 = AgentState::empty()->withMessages(
    Messages::fromString("Implement this plan: {$plan}")
);
while ($implementerAgent->hasNextStep($state2)) {
    $state2 = $implementerAgent->nextStep($state2);
}
```

**Problem**: Manual orchestration, no automatic agent selection.

### Enhanced Approach (Auto-Orchestration)

```php
<?php
// Agents defined in markdown
// File: agents/planner.md
/*
---
name: planner
description: Software architect that creates implementation plans
whenToUse: Use when user asks how to implement, design, or approach a feature
tools: [read_file, search_files, grep]
prohibitedTools: [write_file, edit_file]
constraints: [read-only, planning-only]
---

# Implementation Planner

You are a software architect. Create detailed implementation plans by:
1. Exploring codebase to understand patterns
2. Designing approach that follows existing conventions
3. Breaking down work into actionable steps

You CANNOT implement code - only plan.
*/

// File: agents/implementer.md
/*
---
name: implementer
description: Implements code changes following implementation plans
whenToUse: Use when there's a clear plan to execute and code to write
tools: [read_file, write_file, edit_file, search_files]
capabilities: [code-generation, file-modification]
---

# Code Implementer

You implement code changes following plans. Always:
1. Read existing code first
2. Follow established patterns
3. Test your changes
*/

// Load and run with auto-orchestration
$registry = new AgentRegistry();
$registry->loadFromDirectory(__DIR__ . '/agents');

$mainAgent = AgentBuilder::base()
    ->withSystemPrompt(<<<PROMPT
You are a development assistant. When user requests features:
1. Use planner agent to create implementation plan
2. Review plan with user
3. Use implementer agent to execute plan
PROMPT)
    ->withCapability(new UseSubagents(registry: $registry))
    ->withCapability(new UseAgentSuggestions(registry: $registry))
    ->withAgentDiscovery($registry)
    ->build();

$question = "Add OAuth authentication to the application";
$state = AgentState::empty()->withMessages(Messages::fromString($question));

while ($mainAgent->hasNextStep($state)) {
    $state = $mainAgent->nextStep($state);

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    // Main agent automatically:
    // 1. Detects planning needed → uses planner agent
    // 2. Gets plan from planner
    // 3. Detects implementation needed → uses implementer agent
    // 4. Implementer executes plan
}
```

**Benefit**: Main agent orchestrates planner → implementer workflow automatically.

---

## Example 4: Background Execution

### Current Approach

```php
<?php
// Blocking execution - have to wait for long-running agent
$registry = new AgentRegistry();
$registry->register(new AgentSpec(
    name: 'analyzer',
    description: 'Deep codebase analysis',
    systemPrompt: 'Perform comprehensive analysis.',
    tools: ['read_file', 'search_files', 'grep'],
));

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

$state = AgentState::empty()->withMessages(
    Messages::fromString("Analyze entire codebase for technical debt")
);

// This blocks for minutes...
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
}

echo "Analysis complete!\n";
```

**Problem**: Long-running agents block main execution.

### Enhanced Approach (Background Execution)

```php
<?php
use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\UseSubagents;

$registry = new AgentRegistry();
$registry->loadFromDirectory(__DIR__ . '/agents');

$subagents = new UseSubagents(registry: $registry);

// Spawn analyzer in background
$agentId = $subagents->spawnBackground(
    agentName: 'analyzer',
    initialState: AgentState::empty()->withMessages(
        Messages::fromString("Analyze entire codebase for technical debt")
    )
);

echo "Analysis started in background (ID: {$agentId})\n";

// Continue with other work
echo "Doing other work...\n";
sleep(5);

// Check if analysis complete
$result = $subagents->getResult($agentId);
if ($result === null) {
    echo "Still analyzing...\n";
} else {
    echo "Analysis complete!\n";
    echo $result->currentStep()->outputMessages()->toString();
}
```

**Benefit**: Long-running agents don't block main execution.

---

## Example 5: Agent Resume After Interruption

### Current Approach

```php
<?php
// No way to resume - have to start over
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

$state = AgentState::empty()->withMessages(
    Messages::fromString("Migrate database schema")
);

// Runs for 10 minutes... then crashes
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
}
// If crash happens, all progress lost
```

**Problem**: No way to resume interrupted agents.

### Enhanced Approach (Checkpoint/Resume)

```php
<?php
use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\UseSubagents;

$subagents = new UseSubagents(registry: $registry);

// Start long-running migration
$state = AgentState::empty()->withMessages(
    Messages::fromString("Migrate database schema")
);

$checkpointFile = sys_get_temp_dir() . '/agent_checkpoint.dat';

try {
    while ($agent->hasNextStep($state)) {
        $state = $agent->nextStep($state);

        // Checkpoint every 10 steps
        if ($state->stepCount() % 10 === 0) {
            $checkpoint = $state->checkpoint();
            file_put_contents($checkpointFile, $checkpoint);
            echo "Checkpointed at step {$state->stepCount()}\n";
        }
    }
} catch (\Exception $e) {
    echo "Error occurred: {$e->getMessage()}\n";
    echo "State saved at step {$state->stepCount()}\n";
}

// Later: Resume from checkpoint
if (file_exists($checkpointFile)) {
    echo "Resuming from checkpoint...\n";
    $checkpoint = file_get_contents($checkpointFile);
    $resumedState = $subagents->resume($checkpoint);

    // Continue from where we left off
    while ($agent->hasNextStep($resumedState)) {
        $resumedState = $agent->nextStep($resumedState);
    }

    echo "Migration completed!\n";
}
```

**Benefit**: Agents can be interrupted and resumed without losing progress.

---

## Example 6: Best-Fit Agent Selection

### Current Approach

```php
<?php
// Main agent has to hardcode which subagent to use
$agent = AgentBuilder::base()
    ->withSystemPrompt(<<<PROMPT
When user asks about code structure, use 'explorer' agent.
When user asks about implementation, use 'planner' agent.
When user asks about bugs, use 'reviewer' agent.
PROMPT)
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();
```

**Problem**: Manual routing logic in system prompt, not flexible.

### Enhanced Approach (LLM-Based Selection)

```php
<?php
$registry = new AgentRegistry();
$registry->loadFromDirectory(__DIR__ . '/agents');

// Main agent can query registry for best match
$question = "Find all places where user authentication happens";

// Option 1: Main agent's LLM selects based on agent descriptions (built into prompt)
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->withAgentDiscovery($registry)  // Adds all agent descriptions to prompt
    ->build();

$state = AgentState::empty()->withMessages(Messages::fromString($question));

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
    // Main agent's LLM sees:
    // - explorer: "Use when searching for code patterns, files, or understanding structure"
    // - planner: "Use when designing implementation approach"
    // - reviewer: "Use when analyzing code quality or bugs"
    //
    // LLM selects 'explorer' based on question context
}

// Option 2: Explicit best-match using dedicated LLM call
$instructor = // ... configure instructor
$bestMatch = $registry->findBestMatch($question, $instructor);
echo "Best agent for this task: {$bestMatch->name}\n";

// Then spawn that agent
$selectedState = AgentState::empty()->withMessages(Messages::fromString($question));
// ... execute with bestMatch agent
```

**Benefit**: Flexible, context-aware agent selection without hardcoded rules.

---

## Example 7: Multi-Agent Parallel Execution

### Current Approach

```php
<?php
// Sequential execution - slow
$questions = [
    "Find all API endpoints",
    "Find all database models",
    "Find all test files",
];

$results = [];
foreach ($questions as $question) {
    $state = AgentState::empty()->withMessages(Messages::fromString($question));
    while ($agent->hasNextStep($state)) {
        $state = $agent->nextStep($state);
    }
    $results[] = $state->currentStep()->outputMessages()->toString();
}

echo "All analyses complete\n";
```

**Problem**: Sequential = 3x time. Each analysis blocks next.

### Enhanced Approach (Parallel Background Execution)

```php
<?php
$registry = new AgentRegistry();
$registry->loadFromDirectory(__DIR__ . '/agents');

$subagents = new UseSubagents(registry: $registry);

$questions = [
    "Find all API endpoints",
    "Find all database models",
    "Find all test files",
];

// Spawn all agents in parallel
$agentIds = [];
foreach ($questions as $question) {
    $agentId = $subagents->spawnBackground(
        agentName: 'explorer',
        initialState: AgentState::empty()->withMessages(
            Messages::fromString($question)
        )
    );
    $agentIds[$question] = $agentId;
    echo "Spawned agent {$agentId} for: {$question}\n";
}

// Wait for all to complete
$results = [];
while (count($results) < count($questions)) {
    foreach ($agentIds as $question => $agentId) {
        if (isset($results[$question])) {
            continue;  // Already got result
        }

        $result = $subagents->getResult($agentId);
        if ($result !== null) {
            $results[$question] = $result->currentStep()->outputMessages()->toString();
            echo "✓ Completed: {$question}\n";
        }
    }

    if (count($results) < count($questions)) {
        sleep(1);  // Poll interval
    }
}

echo "\nAll analyses complete!\n";
foreach ($results as $question => $answer) {
    echo "\n{$question}:\n{$answer}\n";
}
```

**Benefit**: 3 agents run in parallel → ~3x faster.

---

## Example 8: Thoroughness Levels

### Current Approach

```php
<?php
// No way to specify thoroughness - always same depth
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

$state = AgentState::empty()->withMessages(
    Messages::fromString("Find API endpoints")
);

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
}
// Always does same amount of work
```

**Problem**: Can't control search depth/thoroughness.

### Enhanced Approach (Configurable Thoroughness)

```php
<?php
// Agent definition with thoroughness support
// File: agents/explorer.md
/*
---
name: explorer
thoroughnessLevels: [quick, medium, thorough]
---

# Explorer Agent

Adapt your search approach based on thoroughness level:

- **quick**: Single-pass search, first good matches
- **medium**: Multi-pass search, verify findings
- **thorough**: Exhaustive search, cross-reference multiple sources
*/

$registry = new AgentRegistry();
$registry->loadFromDirectory(__DIR__ . '/agents');

// Quick search
$quickState = AgentState::empty()
    ->withMessages(Messages::fromString("Find API endpoints"))
    ->withMetadata('thoroughness', 'quick');

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->withAgentDiscovery($registry)
    ->build();

while ($agent->hasNextStep($quickState)) {
    $quickState = $agent->nextStep($quickState);
}
// Takes 10 seconds, returns basic results

// Thorough search
$thoroughState = AgentState::empty()
    ->withMessages(Messages::fromString("Find all API endpoints, including dynamic routes"))
    ->withMetadata('thoroughness', 'thorough');

while ($agent->hasNextStep($thoroughState)) {
    $thoroughState = $agent->nextStep($thoroughState);
}
// Takes 60 seconds, returns comprehensive results
```

**Benefit**: Can trade speed for thoroughness based on need.

---

## Summary of Benefits

| Feature | Current | Enhanced |
|---------|---------|----------|
| **Agent Discovery** | Manual registration | Auto-discovery from markdown |
| **Agent Selection** | Hardcoded rules | LLM-based best-match |
| **Tool Constraints** | No enforcement | Prohibited tools validated |
| **Auto-Suggestions** | No suggestions | Pattern-based suggestions |
| **Background Exec** | Blocking only | Background + result polling |
| **Resume** | No resume | Checkpoint/resume support |
| **Parallel Execution** | Sequential | Spawn multiple in parallel |
| **Thoroughness** | Fixed depth | Configurable levels |
| **Agent Metadata** | Basic | Rich (whenToUse, capabilities, constraints) |
| **Error Prevention** | Runtime errors | Compile-time validation |

The enhanced system provides more flexibility, better performance, and clearer agent roles while maintaining backward compatibility.
