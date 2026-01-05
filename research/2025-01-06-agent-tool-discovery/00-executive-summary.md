# Tool Discovery & Progressive Disclosure: Executive Summary

**Date**: 2025-01-06
**Context**: Design discussion on agent tool/subagent discovery mechanisms
**Status**: Architectural decision

## TL;DR

**Don't inject all tools/agents into context. Instead, make discovery itself a tool.**

Key insight: **"Tool Registry IS A TOOL"** - The discovery mechanism should be a callable tool that follows progressive disclosure patterns, not upfront context injection.

This applies to:
- Tool discovery (file tools, batch tools, CRM tools, etc.)
- Agent/subagent discovery
- Any registry or catalog the agent might need

**Impact**: Dramatically reduced context usage while maintaining full capability access.

---

## The Problem: Context Explosion

### Current Anti-Pattern (Context Injection)

```
System Prompt:

Available tools:
- read_file: Reads a file from the filesystem. Supports multiple formats
  including text, JSON, YAML, CSV. Can read specific line ranges using
  offset and limit parameters. Handles large files efficiently...
  Parameters:
    - file_path (string, required): Absolute path to file. Must exist...
    - offset (integer, optional): Line number to start reading from...
    - limit (integer, optional): Number of lines to read...
  Examples:
    - read_file(file_path="/config/app.yaml")
    - read_file(file_path="/logs/error.log", offset=100, limit=50)

- write_file: Writes content to a file on the filesystem. Creates file
  if it doesn't exist, overwrites if it does. Supports atomic writes...
  Parameters:
    - file_path (string, required): Absolute path to file...
    - content (string, required): Content to write...
    - mode (string, optional): Write mode (overwrite|append)...
  Examples:
    ...

[... 48 more tools with full documentation ...]

Available agents:
- explorer: Read-only codebase exploration specialist. Searches for files,
  patterns, and code using glob, grep, and read operations. Ideal for...
  When to use: Use when you need to find code, understand structure...
  Tools: read_file, search_files, grep
  Constraints: Cannot modify files, read-only mode
  System prompt: You are a codebase exploration specialist...

[... 20 more agents with full specs ...]
```

**Problems**:
- 🔴 **Context explosion**: 10,000+ tokens before agent does anything
- 🔴 **Information overload**: Agent sees irrelevant tools
- 🔴 **Cost**: Paying for unused context every request
- 🔴 **Not scalable**: Can't have 100+ tools

---

## The Solution: Discovery Tools + Progressive Disclosure

### New Pattern (Discovery as Tool)

```
System Prompt:

You have access to discovery tools:
- discover_file_tools: List available file operation tools
- discover_batch_tools: List available batch/shell command tools
- discover_crm_tools: List available CRM API tools
- discover_agents: List available specialized agents

When you need a capability, call the relevant discovery tool first.
```

**Benefits**:
- ✅ **Minimal context**: ~200 tokens vs 10,000+ tokens
- ✅ **On-demand**: Fetch only what's needed
- ✅ **Scalable**: Can have unlimited tools/agents
- ✅ **Cost efficient**: Don't pay for unused context

---

## Progressive Disclosure: CLI-Style Help

Follow standard CLI pattern: `command` → `command help` → `command --help`

### Level 1: List (Summary Only)

**Agent calls**: `discover_file_tools()`

**Response**:
```json
{
  "tools": [
    {"name": "read_file", "summary": "Read file contents"},
    {"name": "write_file", "summary": "Write to file"},
    {"name": "search_files", "summary": "Find files by pattern"},
    {"name": "edit_file", "summary": "Edit file with replacements"}
  ]
}
```

**Cost**: ~10 tokens per tool × 10 tools = **100 tokens**

### Level 2: Details (When Relevant)

**Agent calls**: `discover_file_tools(tool: "read_file")`

**Response**:
```json
{
  "name": "read_file",
  "description": "Reads a file from the filesystem. Supports line ranges.",
  "parameters": ["file_path", "offset", "limit"],
  "usage": "read_file(file_path='/path/to/file.txt')"
}
```

**Cost**: ~50 tokens per tool × 2 relevant tools = **100 tokens**

### Level 3: Full Spec (Rarely Needed)

**Agent calls**: `discover_file_tools(tool: "read_file", parameter: "offset")`

**Response**:
```json
{
  "name": "offset",
  "type": "integer",
  "description": "Line number to start reading from (1-indexed)",
  "required": false,
  "default": 1,
  "examples": ["offset=100 (start at line 100)"]
}
```

**Cost**: ~50 tokens × 1 parameter = **50 tokens**

**Total context**: ~250 tokens vs 10,000+ tokens with full injection

---

## Context Cost Comparison

| Approach | Initial Context | Typical Usage | Worst Case |
|----------|----------------|---------------|------------|
| **Context Injection** | 10,000 tokens | 10,000 tokens | 10,000 tokens |
| **Discovery Tool** | 200 tokens | 200 + 250 = 450 tokens | 200 + 1,000 = 1,200 tokens |
| **Savings** | 98% | 95% | 88% |

Even in worst case (agent explores many tools), discovery is 88% more efficient.

---

## Multiple Discovery Tools Pattern

Different registries for different domains:

```php
$agent = AgentBuilder::base()
    // Domain-specific tool discovery
    ->withTool(new DiscoverToolsTool(
        name: 'discover_file_tools',
        category: 'file operations',
        registry: $fileToolRegistry,
    ))
    ->withTool(new DiscoverToolsTool(
        name: 'discover_batch_tools',
        category: 'batch/shell commands',
        registry: $batchToolRegistry,
    ))
    ->withTool(new DiscoverToolsTool(
        name: 'discover_crm_tools',
        category: 'CRM API operations',
        registry: $crmToolRegistry,
    ))
    ->withTool(new DiscoverToolsTool(
        name: 'discover_database_tools',
        category: 'database operations',
        registry: $dbToolRegistry,
    ))

    // Agent discovery
    ->withTool(new DiscoverAgentsTool($agentRegistry))

    // Subagent spawning
    ->withCapability(new UseSubagents($agentRegistry))
    ->build();
```

**Agent workflow**:
```
User: "Read the database config and update the CRM"

Agent: [Calls discover_file_tools()]
Response: {"tools": [{"name": "read_file", ...}, ...]}

Agent: [Calls read_file("config/database.yaml")]
Response: {database config contents}

Agent: [Calls discover_crm_tools()]
Response: {"tools": [{"name": "update_contact", ...}, ...]}

Agent: [Calls discover_crm_tools(tool: "update_contact")]
Response: {usage details for update_contact}

Agent: [Calls update_contact(...)]
```

Each discovery call is **on-demand** and **contextual**.

---

## Implementation Principles

### 1. Tool Registry IS A Tool

The discovery mechanism is itself a tool:

```
ToolRegistry
  ├── read_file (tool)
  ├── write_file (tool)
  ├── search_files (tool)
  └── discover_tools (tool that reveals the registry)
```

Clean, recursive, self-consistent.

### 2. Progressive Disclosure Levels

Every discovery tool must support 3 levels:

- **Level 1 (Browse)**: Names + one-line summaries
- **Level 2 (Decide)**: Description + parameters + usage
- **Level 3 (Deep dive)**: Full spec + examples + edge cases

Agent decides depth based on relevance.

### 3. No Premature Optimization

Don't inject context "just in case" - let agent pull what it needs.

**Bad**:
```
"Here's everything you might ever need..."
```

**Good**:
```
"When you need something, call discover_* to see what's available"
```

### 4. Category-Based Organization

Group tools by domain:
- File operations
- Batch/shell commands
- API operations (CRM, database, etc.)
- Agents/subagents

Each category has its own discovery tool.

---

## Backward Compatibility

This pattern is **purely additive**:

**Old code (still works)**:
```php
$agent = AgentBuilder::base()
    ->withTool(new ReadFileTool())
    ->withTool(new WriteFileTool())
    // ... all tools explicitly added
    ->build();
```

**New code (more efficient)**:
```php
$agent = AgentBuilder::base()
    ->withTool(new DiscoverToolsTool('discover_file_tools', $fileRegistry))
    // Agent discovers tools on-demand
    ->build();
```

Both approaches work. New pattern is opt-in.

---

## Comparison to Claude Code

**Claude Code's Task tool** follows this pattern:

The Task tool description contains the agent catalog. When main model reads Task tool description, it sees available agents. This is **discovery embedded in tool description**.

Our approach is similar but more explicit:
- Claude Code: Discovery in tool description
- Our system: Discovery as explicit tool calls with progressive levels

Both avoid context injection.

---

## Success Metrics

### Before (Context Injection)
- ❌ 10,000+ tokens in system prompt
- ❌ Can't scale beyond ~50 tools
- ❌ Agent sees irrelevant information
- ❌ High cost per request

### After (Discovery Tools)
- ✅ ~200 tokens in system prompt
- ✅ Can scale to 1,000+ tools
- ✅ Agent sees only relevant information
- ✅ 95%+ context reduction
- ✅ Progressive disclosure follows CLI patterns
- ✅ Multiple domain-specific registries

---

## Implementation Roadmap

### Phase 1: Core Discovery Tool
- [ ] Create `DiscoverToolsTool` with 3 disclosure levels
- [ ] Create `ToolRegistry` to manage tool specs
- [ ] Add `ToolSpec` with short/long/full descriptions
- [ ] Update examples to use discovery

### Phase 2: Agent Discovery
- [ ] Create `DiscoverAgentsTool` with 3 disclosure levels
- [ ] Add discovery levels to `AgentSpec`
- [ ] Integration with `UseSubagents`
- [ ] Update agent examples

### Phase 3: Domain-Specific Registries
- [ ] File tools registry
- [ ] Batch tools registry
- [ ] API tools registry (example: CRM)
- [ ] Database tools registry
- [ ] Documentation and examples

### Phase 4: Polish
- [ ] Performance optimization
- [ ] Caching for repeated discoveries
- [ ] Error handling for invalid tool names
- [ ] Comprehensive test coverage

---

## Key Takeaways

1. **Discovery is a tool, not context injection**
2. **Progressive disclosure reduces context by 95%+**
3. **Follow CLI help patterns** (list → details → full spec)
4. **Multiple registries for different domains**
5. **Agent pulls what it needs, when it needs it**
6. **Scales to unlimited tools/agents**

---

## References

- `01-discovery-as-tool.md` - Conceptual overview and patterns
- `02-progressive-disclosure.md` - Three-level disclosure system
- `03-implementation-guide.md` - Step-by-step implementation
- `04-examples.md` - Code examples and workflows

**Next Steps**: Review with team → Begin Phase 1 implementation
