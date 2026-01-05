# Agent Architecture Comparison: Claude Code vs instructor-php

**Date**: 2026-01-05
**Purpose**: Analyze Claude Code's agent system and compare with our current implementation to guide future development

## Executive Summary

Claude Code uses a **description-based agent selection model** where:
- Main model has access to a Task tool that lists all available subagents with descriptions
- Main model selects the appropriate subagent based on task requirements
- Each subagent is a specialized instance with constrained tools and role-specific instructions

Our system uses an **explicit registry-based model** where:
- Agents are defined via AgentSpec and registered in AgentRegistry
- Main agent spawns subagents via UseSubagents capability
- Selection is based on matching agent names/descriptions

**Key insight**: Claude Code's approach enables both "find best agent" (main model selects) and "use specific agent" (caller specifies subagent_type) patterns.

---

## Claude Code's Agent Architecture

### 1. Core Components

#### A. Task Tool (Agent Launcher)
- **Location**: Main model's tool set
- **Purpose**: Launch specialized subagents for complex tasks
- **Parameters**:
  - `subagent_type`: Which agent to launch (e.g., "Explore", "Plan")
  - `prompt`: Task description for the agent
  - `description`: Short summary (3-5 words) of what agent will do
  - `model`: Optional model override (haiku/sonnet/opus)
  - `run_in_background`: Background execution flag
  - `resume`: Resume previous agent by ID

#### B. Agent Registry (Built-in)
Available subagents listed in Task tool description:

```
Available agent types and the tools they have access to:
- general-purpose: General-purpose agent for researching complex questions (Tools: *)
- statusline-setup: Configure status line setting (Tools: Read, Edit)
- Explore: Fast agent specialized for exploring codebases (Tools: All tools)
- Plan: Software architect agent for designing implementation plans (Tools: All tools)
- claude-code-guide: Answer questions about Claude Code features (Tools: Glob, Grep, Read, WebFetch, WebSearch)
- feature-dev:code-explorer: Deeply analyzes existing codebase features (Tools: Glob, Grep, LS, Read, NotebookRead, WebFetch, TodoWrite, WebSearch, KillShell, BashOutput)
- feature-dev:code-reviewer: Reviews code for bugs and quality issues (Tools: Glob, Grep, LS, Read, NotebookRead, WebFetch, TodoWrite, WebSearch, KillShell, BashOutput)
- feature-dev:code-architect: Designs feature architectures (Tools: Glob, Grep, LS, Read, NotebookRead, WebFetch, TodoWrite, WebSearch, KillShell, BashOutput)
[... 30+ more agents ...]
```

#### C. Agent Definition Structure
Each agent consists of:
- **Name/Type**: Identifier (e.g., `Explore`, `Plan`, `feature-dev:code-reviewer`)
- **Description**: When to use this agent (shown to main model)
- **System Prompt**: Role definition, constraints, instructions
- **Tool Access**: Which tools are available (`All tools`, specific list, or `*`)
- **Read-only Mode**: Many agents explicitly cannot modify files

### 2. Agent Selection Strategies

#### Strategy 1: Main Model Selects (Best-fit)
Main model reads Task tool description, sees all available agents, and chooses based on task context.

**Example**:
```
User: "Where are errors from the client handled?"
Main Model: [Reads Task tool description, sees Explore agent for codebase exploration]
Main Model: [Calls Task tool with subagent_type='Explore']
```

**Key mechanism**:
- Task tool description lists ALL agents with when-to-use guidance
- Main model uses judgment to match task → agent
- Flexible, context-aware selection

#### Strategy 2: Explicit Selection (Specific agent)
System instructions or main model explicitly specifies which agent to use.

**Example from system prompt**:
```markdown
When exploring the codebase to gather context, it is CRITICAL that you use the Task tool
with subagent_type=Explore instead of running search commands directly.
```

**Key mechanism**:
- System prompt encodes routing rules
- Main model instructed to use specific agent for specific patterns
- Deterministic, predictable selection

### 3. Tool Assignment

Claude Code uses **per-agent tool constraints**:

```markdown
# Explore agent
- Tools: Glob, Grep, Read, Bash (read-only operations only)
- Explicitly PROHIBITED from: Write, Edit, any file modifications

# Plan agent
- Tools: Glob, Grep, Read, Bash (read-only operations only)
- Explicitly PROHIBITED from: Write, Edit, any file modifications

# feature-dev:code-reviewer agent
- Tools: Glob, Grep, LS, Read, NotebookRead, WebFetch, TodoWrite, WebSearch, KillShell, BashOutput
- Can use TodoWrite for tracking issues
- Cannot use Write or Edit
```

**Pattern**: Each agent definition explicitly states:
1. Which tools it HAS access to
2. Which tools it CANNOT use (in system prompt)
3. Rationale for constraints

---

## Our Current Architecture

### 1. Core Components

#### A. AgentSpec
Defines a single agent:
```php
new AgentSpec(
    name: 'reader',
    description: 'Reads files and extracts relevant information',
    systemPrompt: 'You read files and extract relevant information. Be thorough and precise.',
    tools: ['read_file'],  // Array of tool names
    model: null,  // Optional LLM override
)
```

#### B. AgentRegistry
Mutable registry of agent specifications:
```php
$registry = new AgentRegistry();
$registry->register($agentSpec);
$agent = $registry->get('reader');
```

Can also load from:
- Markdown files: `loadFromFile($path)`
- Directories: `loadFromDirectory($path)`
- Auto-discovery: `autoDiscover()` (loads from standard locations)

#### C. UseSubagents Capability
Enables main agent to spawn subagents:
```php
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(
        registry: $registry,
        policy: SubagentPolicy::default(),  // maxDepth: 3, summaryMaxChars: 8000
    ))
    ->build();
```

### 2. Agent Selection Strategies

Currently our system primarily supports **explicit selection** but lacks **best-fit selection**.

#### Current: Explicit Selection
Main agent has registry, manually selects subagent by name:
```php
// Agent code would need to explicitly choose which subagent
$subagent = $registry->get('reader');
// Then spawn it somehow
```

**Missing**: Automatic best-fit selection based on task description.

### 3. Tool Assignment

Current approach: **Inheritance with filtering**
- Subagent inherits parent's tools by default
- If `tools: null` → inherits all parent tools
- If `tools: ['tool1', 'tool2']` → filters to those tools only

**Pattern**: Less explicit than Claude Code's approach.

---

## Key Architectural Differences

| Aspect | Claude Code | instructor-php |
|--------|-------------|----------------|
| **Agent Discovery** | Task tool description lists all agents | AgentRegistry stores specs programmatically |
| **Selection Model** | Main model reads descriptions + chooses | Main agent explicitly invokes by name |
| **Tool Assignment** | Per-agent explicit tool list | Inheritance + filtering |
| **Read-only Enforcement** | System prompt explicitly prohibits modifications | No explicit constraints |
| **Agent Definition** | Markdown files with metadata | PHP AgentSpec objects |
| **Registration** | Built into Task tool description | Runtime registration or file loading |
| **Depth Control** | Single level (main → subagent) | Configurable depth via SubagentPolicy |
| **Background Execution** | Supported via `run_in_background` flag | Not explicitly supported |
| **Agent Resume** | Supported via `resume` parameter with agent ID | Not explicitly supported |

---

## Multi-Agent Selection Patterns

### Pattern 1: Best Agent for Task (Dynamic Selection)

**Claude Code approach**:
1. Main model receives user request
2. Main model has Task tool with all agent descriptions
3. Main model matches task characteristics to agent description
4. Main model invokes Task(subagent_type='best-match', prompt='...')

**Example**:
```
User: "Find all API endpoints in this codebase"
Main Model: [Sees Explore agent: "specialized for exploring codebases"]
Main Model: Task(subagent_type='Explore', prompt='Find all API endpoints')
```

**Our system** (currently missing):
- No mechanism for main agent to see all available agents
- No description-based matching
- Would need to implement:
  1. Registry query method: `$registry->listAll()` → returns all specs with descriptions
  2. Agent selection logic: Main agent reads descriptions, picks best match
  3. Invocation: Spawn selected agent

### Pattern 2: Use Specific Agent (Explicit Selection)

**Claude Code approach**:
- System prompt encodes routing rules
- Example: "When exploring codebases, use Task(subagent_type='Explore')"

**Our system** (supported):
```php
// Explicit agent selection
$reader = $registry->get('reader');
// Then use reader agent
```

Works today, but routing logic must be in agent's system prompt or capability logic.

---

## Context-Based Agent Selection

### How Claude Code Prepares for Context-Based Selection

1. **Rich Agent Descriptions**: Each agent has detailed "when to use" guidance
   ```
   "Explore: Fast agent specialized for exploring codebases. Use when you need to
   quickly find files by patterns, search code for keywords, or answer questions
   about the codebase"
   ```

2. **Tool Access Visibility**: Main model sees which tools each agent has
   ```
   "Explore: (Tools: Glob, Grep, Read, WebFetch, WebSearch)"
   ```

3. **Thoroughness Levels**: Some agents support varying thoroughness
   ```
   "Specify thoroughness level: 'quick', 'medium', or 'very thorough'"
   ```

4. **Prohibition Lists**: Agents explicitly state what they CANNOT do
   ```
   "CRITICAL: READ-ONLY MODE - NO FILE MODIFICATIONS"
   ```

5. **Proactive Triggers**: System prompt guides when to auto-launch agents
   ```
   "VERY IMPORTANT: When exploring the codebase... it is CRITICAL that you use
   the Task tool with subagent_type=Explore"
   ```

### How Our System Could Implement Context-Based Selection

**Missing pieces**:
1. **Agent Description Query**: Method to get all agents with descriptions
2. **Context Matching**: Logic to match current context → best agent
3. **Proactive Triggering**: Capability that monitors state and auto-suggests agents

**Proposed enhancement**:
```php
// AgentRegistry enhancement
public function findBestMatch(string $taskDescription, AgentState $context): ?AgentSpec {
    // Use LLM to match task → agent based on descriptions
    // Consider: tools needed, context, task type
}

// UseSubagents enhancement - auto-suggest agents
public function suggestAgent(AgentState $state): ?string {
    // Analyze current state
    // Return suggested agent name or null
}
```

---

## Recommendations for Development

### 1. Enhance Agent Discovery

**Goal**: Enable "find best agent for task" pattern

**Implementation**:
```php
// AgentRegistry
public function describe(): array {
    // Returns: [['name' => '...', 'description' => '...', 'tools' => [...]], ...]
}

public function toMarkdown(): string {
    // Formats all agents as readable list (for LLM consumption)
}
```

**Usage**:
```php
// Agent system prompt includes registry description
$systemPrompt = "Available subagents:\n" . $registry->toMarkdown();
// LLM sees all options and can select best match
```

### 2. Add Tool Constraint Enforcement

**Goal**: Agents explicitly declare prohibited tools

**Implementation**:
```php
new AgentSpec(
    name: 'explorer',
    description: 'Read-only codebase exploration',
    systemPrompt: '...',
    tools: ['read_file', 'search_files', 'grep'],
    prohibitedTools: ['write_file', 'edit_file'],  // NEW
)
```

**Enforcement**: UseSubagents validates tool calls against prohibitedTools list.

### 3. Implement Proactive Agent Triggers

**Goal**: Auto-suggest agents based on state

**Implementation**:
```php
// New capability: UseAgentSuggestions
class UseAgentSuggestions implements Capability {
    public function process(AgentState $state): AgentState {
        // Analyze state for patterns
        // If pattern matches → suggest agent in metadata
        if ($this->shouldExploreCodebase($state)) {
            return $state->withMetadata('suggested_agent', 'explorer');
        }
    }
}
```

### 4. Add Background Execution

**Goal**: Launch long-running agents in background

**Implementation**:
```php
// UseSubagents enhancement
public function spawnBackground(
    string $agentName,
    AgentState $initialState
): string {  // Returns agent ID
    // Launch agent in background thread/process
    // Return ID for later result retrieval
}

public function getResult(string $agentId): ?AgentState {
    // Retrieve completed agent's final state
}
```

### 5. Support Agent Resume

**Goal**: Continue previous agent from checkpoint

**Implementation**:
```php
// AgentState enhancement
public function checkpoint(): string {
    // Serialize state for later resume
}

// UseSubagents enhancement
public function resume(string $checkpointId): AgentState {
    // Restore agent from checkpoint
}
```

### 6. Create Agent Definition DSL

**Goal**: Define agents in markdown (like Claude Code)

**Format**:
```markdown
---
name: explorer
description: Read-only codebase exploration agent
tools: [read_file, search_files, grep]
prohibited_tools: [write_file, edit_file]
model: claude-sonnet-4
---

# System Prompt

You are a codebase exploration specialist...

## Constraints

You are in READ-ONLY mode. You CANNOT:
- Create files
- Modify files
- Delete files
```

**Loading**:
```php
$spec = AgentSpec::fromMarkdown($filePath);
$registry->register($spec);
```

---

## Conclusion

Claude Code's agent system demonstrates a **mature, production-ready** approach to multi-agent orchestration:

1. **Description-based selection** enables flexible "best agent for task" matching
2. **Explicit tool constraints** prevent agents from exceeding their role
3. **Rich metadata** (thoroughness levels, read-only flags) guides usage
4. **Proactive triggers** encoded in system prompts automate agent selection
5. **Background execution + resume** support long-running tasks

Our current system has **strong foundations** (AgentSpec, AgentRegistry, UseSubagents) but needs:
- Better agent discovery mechanisms
- Description-based selection logic
- Tool constraint enforcement
- Proactive agent suggestions
- Background execution support

**Next steps**: Implement recommendations 1-3 first (discovery, constraints, suggestions) as they provide the highest value with moderate complexity.
