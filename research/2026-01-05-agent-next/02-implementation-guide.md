# Agent System Implementation Guide

**Date**: 2026-01-05
**Purpose**: Concrete guidance on implementing multi-agent selection and orchestration

## Overview

This guide provides step-by-step implementation plans for evolving our agent system to support both "find best agent" and "use specific agent" patterns, inspired by Claude Code's architecture.

---

## Phase 1: Agent Discovery & Description-Based Selection

**Goal**: Enable agents to discover and select other agents based on task requirements

### 1.1 Enhance AgentSpec with Richer Metadata

**Current**:
```php
new AgentSpec(
    name: 'reader',
    description: 'Reads files and extracts relevant information',
    systemPrompt: '...',
    tools: ['read_file'],
)
```

**Enhanced**:
```php
new AgentSpec(
    name: 'reader',
    description: 'Reads files and extracts relevant information',
    whenToUse: 'Use when you need to read and analyze file contents without modifying them. Ideal for: code review, documentation extraction, configuration analysis.',
    systemPrompt: '...',
    tools: ['read_file', 'search_files'],
    prohibitedTools: ['write_file', 'edit_file'],
    capabilities: ['file-reading', 'text-analysis'],
    constraints: ['read-only'],
    thoroughnessLevels: ['quick', 'thorough'],
)
```

**Implementation**:
```php
// packages/addons/src/Agent/Agents/AgentSpec.php
final readonly class AgentSpec
{
    public function __construct(
        public string $name,
        public string $description,
        public string $whenToUse,  // NEW: Detailed usage guidance
        public string $systemPrompt,
        public ?array $tools = null,
        public array $prohibitedTools = [],  // NEW: Explicit prohibitions
        public array $capabilities = [],  // NEW: Tagged capabilities
        public array $constraints = [],  // NEW: Operational constraints
        public array $thoroughnessLevels = ['standard'],  // NEW: Supported levels
        public LLMConfig|string|null $model = null,
        public ?array $skills = null,
        public array $metadata = [],
    ) {}
}
```

### 1.2 Add Discovery Methods to AgentRegistry

**New methods**:
```php
// packages/addons/src/Agent/Agents/AgentRegistry.php
class AgentRegistry
{
    // Existing: register(), get(), loadFromFile(), etc.

    /**
     * Get all registered agents with metadata
     * @return array<string, array{name: string, description: string, whenToUse: string, tools: array, capabilities: array}>
     */
    public function describe(): array {
        $descriptions = [];
        foreach ($this->agents as $name => $spec) {
            $descriptions[$name] = [
                'name' => $spec->name,
                'description' => $spec->description,
                'whenToUse' => $spec->whenToUse,
                'tools' => $spec->tools ?? ['*'],
                'capabilities' => $spec->capabilities,
                'constraints' => $spec->constraints,
            ];
        }
        return $descriptions;
    }

    /**
     * Format agent descriptions as markdown for LLM consumption
     */
    public function toMarkdown(): string {
        $lines = ["# Available Agents\n"];
        foreach ($this->agents as $spec) {
            $lines[] = "## {$spec->name}";
            $lines[] = "**Description**: {$spec->description}";
            $lines[] = "**When to use**: {$spec->whenToUse}";
            $lines[] = "**Tools**: " . implode(', ', $spec->tools ?? ['*']);
            if (!empty($spec->constraints)) {
                $lines[] = "**Constraints**: " . implode(', ', $spec->constraints);
            }
            $lines[] = "";
        }
        return implode("\n", $lines);
    }

    /**
     * Find best matching agent for a task description using LLM
     */
    public function findBestMatch(
        string $taskDescription,
        Instructor $instructor
    ): ?AgentSpec {
        $agentDescriptions = $this->toMarkdown();

        $prompt = <<<PROMPT
Given the following task and available agents, select the most appropriate agent.

Task: {$taskDescription}

{$agentDescriptions}

Return the name of the best agent, or null if none match well.
PROMPT;

        $result = $instructor->respond(
            messages: Messages::fromString($prompt),
            responseModel: new class {
                public ?string $agentName = null;
                public string $reasoning = '';
            }
        );

        return $result->agentName ? $this->get($result->agentName) : null;
    }
}
```

### 1.3 Integrate Discovery into Agent System Prompt

**Usage in AgentBuilder**:
```php
// packages/addons/src/Agent/AgentBuilder.php
class AgentBuilder
{
    public function withAgentDiscovery(AgentRegistry $registry): self {
        // Add registry descriptions to system prompt
        $agentList = $registry->toMarkdown();
        $discoveryPrompt = <<<PROMPT

# Available Subagents

You have access to specialized subagents. When a task matches a subagent's
capabilities, consider delegating to that subagent.

{$agentList}

To use a subagent, call the appropriate capability method with the agent name.
PROMPT;

        $this->systemPrompt .= $discoveryPrompt;
        return $this;
    }
}
```

**Example usage**:
```php
$registry = new AgentRegistry();
$registry->register(new AgentSpec(
    name: 'explorer',
    description: 'Read-only codebase exploration',
    whenToUse: 'Use when you need to search for files, grep code, or understand codebase structure without modifying anything',
    systemPrompt: 'You are a codebase exploration specialist...',
    tools: ['read_file', 'search_files', 'grep'],
    prohibitedTools: ['write_file', 'edit_file'],
    constraints: ['read-only'],
));

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->withAgentDiscovery($registry)  // NEW: Adds agent descriptions to prompt
    ->build();

// Now agent's LLM can see all available subagents and their descriptions
```

---

## Phase 2: Tool Constraint Enforcement

**Goal**: Prevent agents from using tools outside their role

### 2.1 Add Prohibition Enforcement to UseSubagents

**Implementation**:
```php
// packages/addons/src/Agent/Capabilities/Subagent/UseSubagents.php
class UseSubagents implements Capability
{
    public function process(AgentState $state): AgentState {
        // Existing: spawn subagent logic

        // NEW: Validate tool usage against prohibitions
        $spec = $this->registry->get($agentName);
        foreach ($state->toolCalls()->all() as $toolCall) {
            if (in_array($toolCall->name(), $spec->prohibitedTools)) {
                throw new AgentException(
                    "Agent '{$spec->name}' is prohibited from using tool '{$toolCall->name()}'. " .
                    "Constraints: " . implode(', ', $spec->constraints)
                );
            }
        }

        return $state;
    }
}
```

### 2.2 Add Prohibition to System Prompt

**Enhancement**:
```php
// packages/addons/src/Agent/Agents/AgentSpec.php
public function toSystemPrompt(): string {
    $prompt = $this->systemPrompt;

    if (!empty($this->prohibitedTools)) {
        $prompt .= "\n\n## CRITICAL CONSTRAINTS\n";
        $prompt .= "You are STRICTLY PROHIBITED from using the following tools:\n";
        foreach ($this->prohibitedTools as $tool) {
            $prompt .= "- {$tool}\n";
        }

        if (in_array('read-only', $this->constraints)) {
            $prompt .= "\nYou are in READ-ONLY mode. You CANNOT:\n";
            $prompt .= "- Create files\n";
            $prompt .= "- Modify files\n";
            $prompt .= "- Delete files\n";
        }
    }

    return $prompt;
}
```

---

## Phase 3: Proactive Agent Suggestions

**Goal**: Auto-suggest agents based on current state/context

### 3.1 Create AgentSuggestion Capability

**Implementation**:
```php
// packages/addons/src/Agent/Capabilities/Suggestion/UseAgentSuggestions.php
namespace Cognesy\Addons\Agent\Capabilities\Suggestion;

use Cognesy\Addons\Agent\Capabilities\Capability;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Agents\AgentRegistry;

class UseAgentSuggestions implements Capability
{
    public function __construct(
        private AgentRegistry $registry,
        private array $patterns = [],
    ) {}

    public function process(AgentState $state): AgentState {
        $suggestion = $this->detectPattern($state);

        if ($suggestion !== null) {
            return $state->withMetadata('suggested_agent', $suggestion);
        }

        return $state;
    }

    private function detectPattern(AgentState $state): ?string {
        $lastMessage = $state->currentStep()?->outputMessages()->toString() ?? '';

        // Pattern: Codebase exploration queries
        if ($this->matchesPattern($lastMessage, [
            'where is', 'find all', 'search for', 'locate', 'which files'
        ])) {
            return 'explorer';
        }

        // Pattern: Planning requests
        if ($this->matchesPattern($lastMessage, [
            'how should i', 'what approach', 'design', 'plan', 'strategy'
        ])) {
            return 'planner';
        }

        // Pattern: Code review requests
        if ($this->matchesPattern($lastMessage, [
            'review', 'check for bugs', 'analyze', 'quality'
        ])) {
            return 'reviewer';
        }

        return null;
    }

    private function matchesPattern(string $text, array $keywords): bool {
        $lower = strtolower($text);
        foreach ($keywords as $keyword) {
            if (str_contains($lower, strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }
}
```

### 3.2 Integrate Suggestions into Main Loop

**Usage**:
```php
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->withCapability(new UseAgentSuggestions(registry: $registry))
    ->withAgentDiscovery($registry)
    ->build();

$state = AgentState::empty()->withMessages(Messages::fromString($question));

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    // Check for suggestion
    $suggested = $state->metadata()->get('suggested_agent');
    if ($suggested) {
        echo "💡 Suggestion: Use '{$suggested}' agent for this task\n";
        // Optionally: Auto-spawn suggested agent
    }
}
```

---

## Phase 4: Background Execution & Resume

**Goal**: Support long-running agents and resumable execution

### 4.1 Add Background Execution to UseSubagents

**Implementation**:
```php
// packages/addons/src/Agent/Capabilities/Subagent/UseSubagents.php
class UseSubagents implements Capability
{
    private array $backgroundAgents = [];

    public function spawnBackground(
        string $agentName,
        AgentState $initialState
    ): string {
        $agentId = uniqid('agent_', true);

        // Launch agent in background (using async/threads/process)
        $process = $this->launchAsync($agentName, $initialState);

        $this->backgroundAgents[$agentId] = [
            'name' => $agentName,
            'process' => $process,
            'startedAt' => new \DateTimeImmutable(),
            'status' => 'running',
        ];

        return $agentId;
    }

    public function getResult(string $agentId): ?AgentState {
        if (!isset($this->backgroundAgents[$agentId])) {
            return null;
        }

        $agent = $this->backgroundAgents[$agentId];

        if ($agent['status'] === 'completed') {
            return $agent['result'];
        }

        // Check if completed
        if ($agent['process']->isFinished()) {
            $result = $agent['process']->getResult();
            $this->backgroundAgents[$agentId]['status'] = 'completed';
            $this->backgroundAgents[$agentId]['result'] = $result;
            return $result;
        }

        return null;  // Still running
    }

    private function launchAsync(string $agentName, AgentState $state): object {
        // Implementation depends on async approach:
        // - ReactPHP for async
        // - Symfony Process for separate processes
        // - Swoole/OpenSwoole for coroutines
        // Simplified example:
        return new class($agentName, $state) {
            public function __construct(private string $name, private AgentState $state) {}
            public function isFinished(): bool { return true; }  // Stub
            public function getResult(): AgentState { return $this->state; }  // Stub
        };
    }
}
```

### 4.2 Add Checkpoint/Resume Support

**Implementation**:
```php
// packages/addons/src/Agent/Data/AgentState.php (enhancement)
trait HandlesCheckpoints
{
    public function checkpoint(): string {
        return serialize([
            'messages' => $this->messages,
            'steps' => $this->steps,
            'metadata' => $this->metadata,
            'status' => $this->status,
        ]);
    }

    public static function fromCheckpoint(string $checkpoint): self {
        $data = unserialize($checkpoint);
        return new self(
            messages: $data['messages'],
            steps: $data['steps'],
            metadata: $data['metadata'],
            status: $data['status'],
        );
    }
}

// UseSubagents enhancement
public function resume(string $checkpointId): AgentState {
    $checkpoint = $this->loadCheckpoint($checkpointId);
    return AgentState::fromCheckpoint($checkpoint);
}
```

---

## Phase 5: Agent Definition DSL

**Goal**: Define agents in markdown files (similar to Claude Code)

### 5.1 Markdown Format

**File**: `agents/explorer.md`
```markdown
---
name: explorer
description: Read-only codebase exploration agent
whenToUse: Use when you need to search for files, grep code, or understand codebase structure without modifying anything
tools: [read_file, search_files, grep]
prohibitedTools: [write_file, edit_file]
capabilities: [file-reading, code-search, text-analysis]
constraints: [read-only]
thoroughnessLevels: [quick, medium, thorough]
model: claude-sonnet-4
---

# Codebase Explorer Agent

You are a codebase exploration specialist. Your role is to efficiently navigate
and analyze codebases to answer questions and find information.

## Your Strengths

- Rapidly finding files using glob patterns
- Searching code with powerful regex
- Reading and analyzing file contents
- Understanding project structure

## Guidelines

1. Use search_files for broad file pattern matching
2. Use grep for searching file contents with regex
3. Use read_file when you know the specific file path
4. Adapt your search approach based on thoroughness level

## CRITICAL CONSTRAINTS

You are in READ-ONLY mode. You are STRICTLY PROHIBITED from:
- Creating files (no write_file)
- Modifying files (no edit_file)
- Deleting files
- Any file system modifications

Your role is EXCLUSIVELY to search and analyze existing code.

## Thoroughness Levels

- **quick**: Single-pass search, return first good matches
- **medium**: Multi-pass search, verify findings
- **thorough**: Exhaustive search, cross-reference multiple sources
```

### 5.2 Markdown Loader

**Implementation**:
```php
// packages/addons/src/Agent/Agents/AgentSpec.php
class AgentSpec
{
    public static function fromMarkdown(string $filePath): self {
        $content = file_get_contents($filePath);

        // Parse frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            throw new \InvalidArgumentException("Invalid markdown format");
        }

        $frontmatter = Yaml::parse($matches[1]);
        $systemPrompt = trim($matches[2]);

        return new self(
            name: $frontmatter['name'],
            description: $frontmatter['description'],
            whenToUse: $frontmatter['whenToUse'] ?? $frontmatter['description'],
            systemPrompt: $systemPrompt,
            tools: $frontmatter['tools'] ?? null,
            prohibitedTools: $frontmatter['prohibitedTools'] ?? [],
            capabilities: $frontmatter['capabilities'] ?? [],
            constraints: $frontmatter['constraints'] ?? [],
            thoroughnessLevels: $frontmatter['thoroughnessLevels'] ?? ['standard'],
            model: $frontmatter['model'] ?? null,
        );
    }
}
```

**Usage**:
```php
$registry = new AgentRegistry();
$registry->register(AgentSpec::fromMarkdown('agents/explorer.md'));
$registry->register(AgentSpec::fromMarkdown('agents/planner.md'));
$registry->register(AgentSpec::fromMarkdown('agents/reviewer.md'));

// Or load all from directory
$registry->loadFromDirectory('agents/');
```

---

## Complete Example: Multi-Agent System

```php
<?php
require 'vendor/autoload.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Agents\AgentSpec;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Capabilities\Suggestion\UseAgentSuggestions;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

// 1. Load agent definitions from markdown
$registry = new AgentRegistry();
$registry->loadFromDirectory(__DIR__ . '/agents');

// 2. Build main agent with multi-agent capabilities
$mainAgent = AgentBuilder::base()
    ->withSystemPrompt('You are a software development assistant...')
    ->withCapability(new UseSubagents(registry: $registry))
    ->withCapability(new UseAgentSuggestions(registry: $registry))
    ->withAgentDiscovery($registry)  // Adds agent descriptions to prompt
    ->build();

// 3. User asks question
$question = "Where are all the API routes defined in this codebase?";
$state = AgentState::empty()->withMessages(Messages::fromString($question));

echo "Question: {$question}\n\n";

// 4. Main agent processes (with agent discovery)
while ($mainAgent->hasNextStep($state)) {
    $state = $mainAgent->nextStep($state);

    // Check if agent suggested using a subagent
    $suggested = $state->metadata()->get('suggested_agent');
    if ($suggested) {
        echo "💡 Detected pattern → suggesting '{$suggested}' agent\n";

        // Main agent can now see explorer in its prompt and choose to use it
        // OR we can auto-spawn:
        // $subagentState = $registry->get($suggested)->execute($state);
    }

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";
}

// 5. Get final answer
$answer = $state->currentStep()?->outputMessages()->toString() ?? 'No answer';
echo "\nFinal Answer:\n{$answer}\n";

// Main agent likely used 'explorer' subagent automatically based on:
// 1. Suggestion capability detected pattern
// 2. Agent discovery showed explorer is available
// 3. Main agent's LLM selected explorer based on task match
```

---

## Testing Strategy

### Unit Tests

**Test agent discovery**:
```php
test('AgentRegistry describes all registered agents', function() {
    $registry = new AgentRegistry();
    $registry->register(new AgentSpec(
        name: 'test-agent',
        description: 'Test agent',
        whenToUse: 'Use for testing',
        systemPrompt: 'Test prompt',
        tools: ['tool1', 'tool2'],
    ));

    $descriptions = $registry->describe();
    expect($descriptions)->toHaveKey('test-agent');
    expect($descriptions['test-agent']['tools'])->toBe(['tool1', 'tool2']);
});
```

**Test tool prohibition enforcement**:
```php
test('UseSubagents prevents prohibited tool usage', function() {
    $registry = new AgentRegistry();
    $registry->register(new AgentSpec(
        name: 'reader',
        description: 'Read-only agent',
        systemPrompt: 'You read files',
        tools: ['read_file'],
        prohibitedTools: ['write_file'],
        constraints: ['read-only'],
    ));

    $capability = new UseSubagents(registry: $registry);

    // Create state with prohibited tool call
    $state = AgentState::empty()
        ->withToolCall(new ToolCall('write_file', [...]));

    expect(fn() => $capability->process($state))
        ->toThrow(AgentException::class, 'prohibited from using tool');
});
```

### Integration Tests

**Test best-match selection**:
```php
test('findBestMatch selects appropriate agent', function() {
    $registry = new AgentRegistry();
    $registry->loadFromDirectory(__DIR__ . '/fixtures/agents');

    $instructor = // ... configure instructor

    $match = $registry->findBestMatch(
        'Find all API endpoints in the codebase',
        $instructor
    );

    expect($match)->not->toBeNull();
    expect($match->name)->toBe('explorer');
});
```

---

## Migration Path

### Step 1: Enhance Core Classes (Week 1)
- Add new fields to AgentSpec
- Add discovery methods to AgentRegistry
- Maintain backward compatibility

### Step 2: Implement Constraints (Week 2)
- Add prohibition enforcement to UseSubagents
- Update system prompt generation
- Add validation tests

### Step 3: Add Suggestions (Week 3)
- Implement UseAgentSuggestions capability
- Test pattern detection
- Document patterns

### Step 4: Background Execution (Week 4)
- Research async approach (ReactPHP/Swoole/Process)
- Implement basic background spawning
- Add checkpoint/resume

### Step 5: Markdown DSL (Week 5)
- Define markdown format
- Implement parser
- Convert existing agents to markdown
- Update examples

### Step 6: Documentation & Examples (Week 6)
- Update agent examples
- Write migration guide
- Create new examples demonstrating multi-agent patterns

---

## Conclusion

This implementation guide provides a clear path to evolve our agent system to support:
- ✅ Dynamic "find best agent" selection
- ✅ Explicit "use specific agent" invocation
- ✅ Tool constraint enforcement
- ✅ Proactive agent suggestions
- ✅ Background execution & resume
- ✅ Markdown-based agent definitions

The design maintains backward compatibility while adding powerful new capabilities inspired by Claude Code's proven architecture.
