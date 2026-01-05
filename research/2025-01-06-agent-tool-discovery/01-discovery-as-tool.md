# Discovery as Tool: Conceptual Overview

**Date**: 2025-01-06
**Purpose**: Deep dive into "Tool Registry IS A TOOL" pattern

## Core Concept

**Traditional approach**: Inject all available tools/agents into system prompt upfront.

**New approach**: Make discovery itself a callable tool that the agent invokes when needed.

---

## The Fundamental Insight

A registry/catalog is just data. An agent accessing that data is a capability. Therefore, **the registry accessor should be a tool**.

```
Registry (data) + Accessor (capability) = Discovery Tool
```

This creates an elegant recursive structure:

```
ToolRegistry
  ├── read_file        (tool for reading files)
  ├── write_file       (tool for writing files)
  ├── search_files     (tool for searching files)
  └── discover_tools   (tool for discovering other tools)
```

The discovery tool **reveals** the registry, but doesn't dump everything at once.

---

## Why Discovery Should Be a Tool

### 1. Consistency

Everything the agent can do is a tool. Discovery is something the agent can do. Therefore, discovery should be a tool.

**Inconsistent** (mixing paradigms):
```php
System prompt: Here's a list of 50 tools...  // Static injection
Tools: [read_file, write_file, ...]          // Dynamic invocation
```

**Consistent** (single paradigm):
```php
System prompt: You have discovery tools available  // Mentions capability
Tools: [discover_file_tools, discover_agents, ...]  // All tools, including discovery
```

### 2. Lazy Loading

Don't load what you don't need.

**Eager loading** (traditional):
```
Request arrives
  → Load ALL tool specs into context (10,000 tokens)
  → Agent uses 2 tools
  → Paid for 48 unused tool specs
```

**Lazy loading** (discovery tool):
```
Request arrives
  → Minimal context (200 tokens)
  → Agent calls discover_file_tools() if needed
  → Gets 10 file tool names (100 tokens)
  → Calls discover_file_tools(tool: "read_file") for details (50 tokens)
  → Uses read_file
  → Total: 350 tokens vs 10,000 tokens
```

### 3. Scalability

Static injection doesn't scale. Discovery does.

**Context limits with injection**:
```
10 tools × 200 tokens = 2,000 tokens       ✅ Fine
50 tools × 200 tokens = 10,000 tokens      ⚠️  Getting expensive
100 tools × 200 tokens = 20,000 tokens     🔴 Approaching context limits
500 tools × 200 tokens = 100,000 tokens    🔴 Impossible
```

**Context usage with discovery**:
```
Baseline: 200 tokens (discovery tool descriptions)
+ Level 1: 10 tokens per tool name
+ Level 2: 50 tokens per relevant tool
+ Level 3: 200 tokens per detailed spec

Typical usage with 100 tools:
  200 (baseline)
  + 100 × 10 (list all)
  + 3 × 50 (details for 3 relevant)
  + 1 × 200 (deep dive on 1)
  = 1,550 tokens

Can scale to 1,000 tools with same pattern.
```

### 4. Dynamic Context

Tool availability can change at runtime.

**Static injection**:
```php
// Tools fixed at agent build time
$agent = AgentBuilder::base()
    ->withTool(new ReadFileTool())
    ->withTool(new WriteFileTool())
    ->build();

// Later: New tool added to system
// Agent doesn't know about it (would need rebuild)
```

**Discovery tool**:
```php
// Tools loaded from registry at runtime
$registry->register(new ReadFileTool());
$registry->register(new WriteFileTool());

$agent = AgentBuilder::base()
    ->withTool(new DiscoverToolsTool($registry))
    ->build();

// Later: New tool added
$registry->register(new NewTool());
// Agent discovers it automatically on next discovery call
```

### 5. Categorization

Different discovery tools for different domains.

**Single monolithic registry**:
```
discover_tools() → 100 tools from all domains
  ❌ File tools mixed with API tools mixed with batch tools
  ❌ Hard to navigate
  ❌ Cognitive overload
```

**Multiple domain-specific registries**:
```
discover_file_tools()     → 10 file operation tools
discover_batch_tools()    → 15 shell/batch tools
discover_crm_tools()      → 20 CRM API tools
discover_database_tools() → 12 database tools
discover_agents()         → 5 specialized agents

✅ Clear categorization
✅ Easy to navigate
✅ Relevant context only
```

---

## Discovery Tool as First-Class Citizen

The discovery tool should have the same status as any other tool:

```php
interface Tool
{
    public function __invoke(array $args): mixed;
    public function getSpec(): ToolSpec;
}

class DiscoverToolsTool implements Tool
{
    public function __construct(
        private string $name,
        private string $category,
        private ToolRegistry $registry,
    ) {}

    public function __invoke(array $args): array {
        // Return tool information based on args
    }

    public function getSpec(): ToolSpec {
        return new ToolSpec(
            name: $this->name,
            description: "Discover available {$this->category} tools",
            parameters: [...],
        );
    }
}
```

The agent sees discovery tools in its tool list, just like any other tool:

```
Available tools:
- discover_file_tools
- discover_batch_tools
- discover_agents
```

When it needs more tools, it **calls** discovery, just like calling any other tool.

---

## Comparison: Injection vs Discovery

### Context Injection Approach

**System prompt**:
```
You have access to these tools:

1. read_file
   Description: Reads a file from the filesystem
   Parameters:
     - file_path (string, required): Path to file
     - offset (integer, optional): Starting line
     - limit (integer, optional): Number of lines
   Usage: read_file(file_path="/path/to/file")

2. write_file
   Description: Writes content to a file
   Parameters:
     - file_path (string, required): Path to file
     - content (string, required): Content to write
   Usage: write_file(file_path="/path/to/file", content="...")

[... 48 more tools ...]
```

**Problems**:
- All tools described upfront (high token cost)
- Agent sees irrelevant tools
- Doesn't scale beyond ~50 tools
- Static - can't add tools at runtime
- No categorization

### Discovery Tool Approach

**System prompt**:
```
You have access to discovery tools:
- discover_file_tools: List file operation tools
- discover_batch_tools: List shell/batch tools
- discover_crm_tools: List CRM API tools
- discover_agents: List specialized agents
```

**Agent workflow**:
```
User: "Read config.yaml"

Agent thinks: "I need to read a file. Let me see what's available."

Agent calls: discover_file_tools()
Response: {
  "tools": [
    {"name": "read_file", "summary": "Read file contents"},
    {"name": "write_file", "summary": "Write to file"},
    {"name": "search_files", "summary": "Find files"}
  ]
}

Agent thinks: "read_file looks right. Let me get usage."

Agent calls: discover_file_tools(tool: "read_file")
Response: {
  "name": "read_file",
  "description": "Reads a file from the filesystem",
  "parameters": ["file_path", "offset", "limit"],
  "usage": "read_file(file_path='/path/to/file')"
}

Agent thinks: "Got it."

Agent calls: read_file(file_path="config.yaml")
```

**Benefits**:
- Minimal upfront context
- Only relevant information retrieved
- Scales to unlimited tools
- Dynamic - registry can change
- Clear categorization

---

## Pattern: Registry as Data + Tool as Interface

This pattern applies beyond tools:

### Tool Discovery
```
ToolRegistry (data)
  + DiscoverToolsTool (interface)
  = On-demand tool catalog access
```

### Agent Discovery
```
AgentRegistry (data)
  + DiscoverAgentsTool (interface)
  = On-demand agent catalog access
```

### API Discovery (future)
```
APIRegistry (data)
  + DiscoverAPITool (interface)
  = On-demand API catalog access
```

### Documentation Discovery (future)
```
DocumentationIndex (data)
  + DiscoverDocsTool (interface)
  = On-demand documentation access
```

**The pattern is universal**: Whenever you have a catalog/registry, make access to it a tool rather than injecting it into context.

---

## Implementation Pattern

### 1. Define Registry (Data)

```php
class ToolRegistry
{
    private array $tools = [];

    public function register(Tool $tool): void {
        $this->tools[$tool->name] = $tool;
    }

    public function get(string $name): ?Tool {
        return $this->tools[$name] ?? null;
    }

    public function all(): array {
        return $this->tools;
    }

    public function describe(): array {
        return array_map(
            fn($tool) => [
                'name' => $tool->name,
                'summary' => $tool->shortDescription,
            ],
            $this->tools
        );
    }
}
```

### 2. Create Discovery Tool (Interface)

```php
class DiscoverToolsTool implements Tool
{
    public function __construct(
        private string $name,
        private ToolRegistry $registry,
    ) {}

    public function __invoke(array $args): array {
        if (empty($args['tool'])) {
            // Level 1: List all
            return ['tools' => $this->registry->describe()];
        }

        // Level 2: Get details for specific tool
        $tool = $this->registry->get($args['tool']);
        return [
            'name' => $tool->name,
            'description' => $tool->description,
            'parameters' => array_keys($tool->parameters),
            'usage' => $tool->usageExample,
        ];
    }

    public function getSpec(): ToolSpec {
        return new ToolSpec(
            name: $this->name,
            description: 'Discover available tools',
            parameters: [
                'tool' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Specific tool to get details for',
                ],
            ],
        );
    }
}
```

### 3. Register Discovery Tool

```php
$toolRegistry = new ToolRegistry();
$toolRegistry->register(new ReadFileTool());
$toolRegistry->register(new WriteFileTool());
$toolRegistry->register(new SearchFilesTool());

$agent = AgentBuilder::base()
    ->withTool(new DiscoverToolsTool(
        name: 'discover_file_tools',
        registry: $toolRegistry,
    ))
    ->build();

// Agent can now discover tools on-demand
```

---

## Edge Cases and Considerations

### When to Use Injection

Discovery tools are NOT always the right choice. Use injection when:

**Small, stable set of core tools** (~5-10 tools):
```php
// These tools are ALWAYS needed, show them upfront
$agent = AgentBuilder::base()
    ->withTool(new AskUserQuestionTool())
    ->withTool(new DiscoverToolsTool($registry))
    ->build();
```

**Critical tools that should be obvious**:
```php
// Don't hide emergency/critical tools behind discovery
System prompt: "CRITICAL: If you encounter errors, use report_error tool immediately"
```

### When to Use Discovery

Use discovery when:
- Large number of tools (50+)
- Tools grouped by domain
- Tool availability changes at runtime
- Most tools are rarely needed
- Context efficiency matters

### Hybrid Approach

Best practice: **Core tools + Discovery**

```php
$agent = AgentBuilder::base()
    // Core tools (always visible)
    ->withTool(new AskUserQuestionTool())
    ->withTool(new ReportErrorTool())

    // Discovery tools (on-demand access)
    ->withTool(new DiscoverToolsTool('discover_file_tools', $fileRegistry))
    ->withTool(new DiscoverToolsTool('discover_batch_tools', $batchRegistry))
    ->withTool(new DiscoverToolsTool('discover_api_tools', $apiRegistry))
    ->withTool(new DiscoverAgentsTool($agentRegistry))

    ->build();
```

Agent sees:
- Core tools immediately
- Discovery tools for extended capabilities

---

## Conclusion

**Tool Registry IS A TOOL** is a powerful pattern that:

1. Maintains consistency (everything is a tool)
2. Enables lazy loading (fetch what you need)
3. Provides scalability (unlimited tools possible)
4. Supports dynamic context (tools can change at runtime)
5. Allows categorization (domain-specific registries)

The pattern treats registries as data and provides tools as the interface to that data, following good separation of concerns and enabling progressive disclosure.

**Next**: See `02-progressive-disclosure.md` for details on the three-level disclosure system.
