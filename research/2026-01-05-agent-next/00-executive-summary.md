# Agent System Enhancement: Executive Summary

**Date**: 2026-01-05
**Author**: Analysis based on Claude Code's agent architecture
**Status**: Recommendations for implementation

## TL;DR

Our current agent system (AgentSpec + AgentRegistry + UseSubagents) has solid foundations but lacks key features for **dynamic agent selection** and **context-aware orchestration**. By adopting patterns from Claude Code's proven architecture, we can enable both "find best agent for task" and "use specific agent" workflows.

**Priority implementations**:
1. **Agent Discovery** - Enable agents to see and select other agents dynamically
2. **Tool Constraints** - Prevent agents from exceeding their role
3. **Auto-Suggestions** - Detect patterns and suggest appropriate agents

**Impact**: More flexible, safer, and more powerful multi-agent orchestration.

---

## Current State vs. Desired State

### Current State ✅

**What works well**:
- ✅ AgentSpec: Clean, type-safe agent definition
- ✅ AgentRegistry: Centralized agent catalog with file loading
- ✅ UseSubagents: Capability-based subagent spawning
- ✅ Depth control: SubagentPolicy prevents infinite recursion
- ✅ Tool inheritance: Subagents can inherit/filter parent tools
- ✅ Immutable state: AgentState tracks execution cleanly

**Architecture**:
```
Main Agent (AgentBuilder)
    ├── Capability: UseSubagents
    │   └── AgentRegistry (knows available agents)
    └── Capability: UseFileTools
```

### Desired State 🎯

**What's missing**:
- ❌ **Agent discovery**: Main agent can't see which agents exist
- ❌ **Best-fit selection**: No mechanism to find "best agent for task"
- ❌ **Tool constraints**: Agents can use any inherited tool (no prohibitions)
- ❌ **Auto-suggestions**: No pattern detection to suggest agents
- ❌ **Background execution**: All agents block until complete
- ❌ **Resume support**: Can't checkpoint/resume interrupted agents
- ❌ **Rich metadata**: Limited "when to use" guidance

**Target architecture**:
```
Main Agent (AgentBuilder)
    ├── Capability: UseSubagents
    │   └── AgentRegistry (discoverable catalog)
    ├── Capability: UseAgentSuggestions (pattern detection)
    ├── Capability: UseFileTools
    └── Enhanced System Prompt:
        - Lists all available agents with descriptions
        - Enables LLM to select best agent for task
```

---

## Claude Code's Agent Architecture (What We Learned)

### 1. Task Tool as Agent Launcher

Claude Code's main model has a **Task tool** that:
- Lists ALL available subagents with descriptions in tool description
- Main model reads descriptions and selects appropriate agent
- Supports both "best-fit" (LLM chooses) and "explicit" (system prompt specifies) selection

**Example from Task tool description**:
```
Available agent types and the tools they have access to:
- Explore: Fast agent specialized for exploring codebases. Use when you need to
  quickly find files by patterns, search code for keywords, or answer questions
  about the codebase. (Tools: Glob, Grep, Read, WebFetch, WebSearch)

- Plan: Software architect agent for designing implementation plans. Use for
  complex tasks requiring architectural decisions and step-by-step planning.
  (Tools: Glob, Grep, Read, Bash-read-only)

[... 30+ more agents ...]
```

Main model sees this → matches task → invokes Task(subagent_type='Explore')

### 2. Explicit Tool Constraints

Each agent explicitly states:
- ✅ Which tools it **HAS**
- 🚫 Which tools it **CANNOT** use
- 📋 Rationale for constraints

**Example from Explore agent**:
```markdown
# Explore Agent

Tools: Glob, Grep, Read, Bash (read-only)

## CRITICAL: READ-ONLY MODE - NO FILE MODIFICATIONS

You are STRICTLY PROHIBITED from:
- Creating files (no Write, touch)
- Modifying files (no Edit)
- Deleting files (no rm)
- Running commands that change system state
```

### 3. Proactive Agent Triggers

System prompt encodes routing rules:
```
VERY IMPORTANT: When exploring the codebase to gather context or to answer
a question that is not a needle query for a specific file/class/function,
it is CRITICAL that you use the Task tool with subagent_type=Explore instead
of running search commands directly.
```

Main model auto-triggers agents based on patterns.

### 4. Rich Agent Metadata

Each agent has:
- **Name**: Identifier (e.g., `Explore`)
- **Description**: Short summary
- **When to use**: Detailed guidance for main model
- **Tools**: Explicit tool list
- **Thoroughness levels**: Some agents support "quick"/"medium"/"thorough"
- **Background execution**: Support for async agents
- **Resume**: Agents can be resumed by ID

---

## Gap Analysis

| Feature | Claude Code | Our System | Gap |
|---------|-------------|------------|-----|
| Agent descriptions visible to main model | ✅ Yes (in Task tool) | ❌ No | **High** |
| LLM-based agent selection | ✅ Yes (main model chooses) | ❌ No | **High** |
| Tool prohibitions | ✅ Yes (system prompt + validation) | ❌ No | **Medium** |
| Proactive agent triggers | ✅ Yes (system prompt rules) | ❌ No | **Medium** |
| Background execution | ✅ Yes (run_in_background flag) | ❌ No | **Low** |
| Agent resume | ✅ Yes (resume parameter + ID) | ❌ No | **Low** |
| Thoroughness levels | ✅ Yes (some agents) | ❌ No | **Low** |
| Markdown agent definitions | ✅ Yes | ⚠️ Partial (can load from file) | **Low** |
| Depth control | ⚠️ Single level | ✅ Yes (SubagentPolicy.maxDepth) | **None** |
| Tool inheritance | ⚠️ Explicit per agent | ✅ Yes (inherit + filter) | **None** |

**Priority gaps**:
1. **High**: Agent discovery + selection
2. **Medium**: Tool constraints + proactive triggers
3. **Low**: Background execution + resume

---

## Recommended Implementation Roadmap

### Phase 1: Foundation (Week 1-2)
**Goal**: Enable agent discovery and best-fit selection

**Tasks**:
- [ ] Enhance AgentSpec with `whenToUse`, `prohibitedTools`, `capabilities`, `constraints`
- [ ] Add `AgentRegistry::describe()` - returns all agents with metadata
- [ ] Add `AgentRegistry::toMarkdown()` - formats agents for LLM
- [ ] Add `AgentBuilder::withAgentDiscovery()` - injects agent list into system prompt
- [ ] Update examples to use discovery

**Outcome**: Main agent can see available agents and select best match

**Example**:
```php
$registry = new AgentRegistry();
$registry->loadFromDirectory('agents/');

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->withAgentDiscovery($registry)  // NEW: Adds agent descriptions to prompt
    ->build();

// Main agent's LLM now sees all available agents and can select best match
```

### Phase 2: Constraints (Week 3)
**Goal**: Enforce tool prohibitions and read-only modes

**Tasks**:
- [ ] Add `prohibitedTools` validation to UseSubagents
- [ ] Add `constraints` to system prompt generation
- [ ] Create `AgentSpec::toSystemPrompt()` that includes prohibitions
- [ ] Add unit tests for constraint violations

**Outcome**: Agents cannot exceed their defined role

**Example**:
```php
new AgentSpec(
    name: 'explorer',
    description: 'Read-only codebase exploration',
    prohibitedTools: ['write_file', 'edit_file'],
    constraints: ['read-only'],
);

// If explorer tries to write_file → AgentException thrown immediately
```

### Phase 3: Auto-Suggestions (Week 4)
**Goal**: Detect patterns and suggest appropriate agents

**Tasks**:
- [ ] Create `UseAgentSuggestions` capability
- [ ] Implement pattern detection (keywords, question types)
- [ ] Add suggestion to state metadata
- [ ] Update examples to show auto-suggestions

**Outcome**: System proactively suggests agents based on context

**Example**:
```php
$agent = AgentBuilder::base()
    ->withCapability(new UseAgentSuggestions(registry: $registry))
    ->build();

// User: "Where are the API routes?"
// → UseAgentSuggestions detects "where are" pattern
// → Sets state metadata: suggested_agent = 'explorer'
// → Main agent sees suggestion and uses explorer
```

### Phase 4: Best-Match Selection (Week 5)
**Goal**: LLM-based agent selection for complex cases

**Tasks**:
- [ ] Add `AgentRegistry::findBestMatch()` using LLM
- [ ] Create selection prompt template
- [ ] Add confidence scoring
- [ ] Add fallback to suggestion-based selection

**Outcome**: Sophisticated agent selection for edge cases

**Example**:
```php
$bestMatch = $registry->findBestMatch(
    "Find all security vulnerabilities in authentication code",
    $instructor
);
// Returns 'security-reviewer' agent (not just 'explorer')
```

### Phase 5: Advanced Features (Week 6+)
**Goal**: Background execution, resume, thoroughness

**Tasks**:
- [ ] Research async approach (ReactPHP/Swoole/Process)
- [ ] Implement `UseSubagents::spawnBackground()`
- [ ] Add checkpoint/resume support
- [ ] Add thoroughness levels to AgentSpec
- [ ] Create markdown agent definition format

**Outcome**: Production-ready multi-agent system

---

## Success Metrics

### Before Implementation
- ❌ Cannot discover available agents programmatically
- ❌ Manual agent selection in every example
- ❌ No tool constraint enforcement
- ❌ No pattern-based suggestions
- ❌ No background execution
- ⚠️ Limited agent metadata

### After Phase 1-3 Implementation (Minimum Viable)
- ✅ Agents can discover other agents via registry
- ✅ LLM-based best-fit selection works
- ✅ Tool prohibitions enforced
- ✅ Pattern-based suggestions active
- ✅ Rich agent metadata (whenToUse, capabilities, constraints)
- ✅ Examples demonstrate multi-agent patterns

### After Phase 4-5 Implementation (Production-Ready)
- ✅ Sophisticated LLM-based selection
- ✅ Background execution for long-running agents
- ✅ Checkpoint/resume for interrupted agents
- ✅ Thoroughness levels for search depth
- ✅ Markdown agent definitions
- ✅ Comprehensive examples

---

## Risk Assessment

### Low Risk ✅
- Agent discovery (pure addition, no breaking changes)
- Auto-suggestions (metadata-based, isolated)
- Markdown definitions (alternative to programmatic)

### Medium Risk ⚠️
- Tool constraints (validation could break existing agents)
  - **Mitigation**: Make prohibitions opt-in initially
- Best-match selection (LLM cost for every selection)
  - **Mitigation**: Cache selection for similar queries

### High Risk 🚨
- Background execution (async complexity, error handling)
  - **Mitigation**: Start with simple process-based approach
  - **Mitigation**: Extensive testing with timeouts

---

## Backward Compatibility

All enhancements designed to be **backward compatible**:

**Existing code continues to work**:
```php
// This still works unchanged
$registry = new AgentRegistry();
$registry->register(new AgentSpec(
    name: 'test',
    description: 'Test agent',
    systemPrompt: 'Test',
    tools: ['read_file'],
));

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();
```

**New features are opt-in**:
```php
// Enhanced features require explicit enablement
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->withCapability(new UseAgentSuggestions(registry: $registry))  // Opt-in
    ->withAgentDiscovery($registry)  // Opt-in
    ->build();
```

**AgentSpec enhancements use defaults**:
```php
new AgentSpec(
    name: 'test',
    description: 'Test',
    whenToUse: 'Test',  // NEW - defaults to description if not provided
    systemPrompt: 'Test',
    tools: ['read_file'],
    prohibitedTools: [],  // NEW - defaults to empty array
    constraints: [],  // NEW - defaults to empty array
)
```

---

## Conclusion

### What We Have ✅
- Solid foundation with AgentSpec, AgentRegistry, UseSubagents
- Clean architecture with capability-based composition
- Type-safe, immutable state management
- Depth control and tool inheritance

### What We Need 🎯
- **Agent discovery** - Make agents discoverable to main agent
- **Tool constraints** - Enforce role boundaries
- **Auto-suggestions** - Proactive agent recommendation
- **Best-fit selection** - LLM-based agent matching
- **Background execution** - Async long-running agents

### Implementation Priority
1. **Phase 1** (High value, low risk): Agent discovery
2. **Phase 2** (High value, medium risk): Tool constraints
3. **Phase 3** (Medium value, low risk): Auto-suggestions
4. **Phase 4** (Medium value, medium risk): Best-match selection
5. **Phase 5** (Low value, high risk): Background execution

### Expected Outcome
A production-ready multi-agent system that:
- Automatically selects appropriate agents based on task
- Enforces role boundaries with tool constraints
- Suggests agents proactively based on patterns
- Supports both explicit and dynamic agent selection
- Maintains backward compatibility with existing code

**Recommendation**: Start with Phase 1-3 (weeks 1-4) for immediate value with minimal risk.

---

## References

- `01-architecture-comparison.md` - Detailed Claude Code vs. our system comparison
- `02-implementation-guide.md` - Step-by-step implementation instructions
- `03-usage-examples.md` - Before/after code examples

**Next Steps**: Review with team → Prioritize phases → Begin Phase 1 implementation
