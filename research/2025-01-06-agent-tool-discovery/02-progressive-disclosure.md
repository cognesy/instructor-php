# Progressive Disclosure: Three-Level System

**Date**: 2025-01-06
**Purpose**: Detailed specification of progressive disclosure for tool/agent discovery

## Core Principle

**Never dump everything. Always allow progressive reveal.**

Discovery tools should follow CLI help patterns:
- `git` → lists commands
- `git help commit` → explains commit
- `git commit --help` → full options

---

## The Three Levels

Every discovery tool MUST support three disclosure levels:

| Level | Purpose | Content | Tokens/Item | When Used |
|-------|---------|---------|-------------|-----------|
| **Level 1** | Browse | Name + one-line summary | ~10 | Always (initial discovery) |
| **Level 2** | Decide | Description + parameters + usage | ~50 | When tool seems relevant |
| **Level 3** | Deep Dive | Full spec + examples + edge cases | ~200 | Rarely (complex usage) |

---

## Level 1: Browse (List Available)

**Purpose**: Quick overview of what exists

**Goal**: Agent scans and identifies potentially relevant tools

**Content**:
- Tool name
- One-line summary (max 10 words)

**Example call**:
```php
discover_file_tools()
```

**Example response**:
```json
{
  "tools": [
    {"name": "read_file", "summary": "Read file contents"},
    {"name": "write_file", "summary": "Write to file"},
    {"name": "search_files", "summary": "Find files by pattern"},
    {"name": "edit_file", "summary": "Edit file with replacements"},
    {"name": "delete_file", "summary": "Delete file"},
    {"name": "list_directory", "summary": "List directory contents"},
    {"name": "create_directory", "summary": "Create new directory"},
    {"name": "copy_file", "summary": "Copy file to destination"},
    {"name": "move_file", "summary": "Move/rename file"}
  ]
}
```

**Token cost**: 10 tokens × 9 tools = **90 tokens**

**Agent workflow**:
```
Agent sees: 9 file tools available
Agent thinks: "I need to read a file. 'read_file' looks relevant."
Agent proceeds to Level 2 for read_file only
```

### Level 1 Design Rules

1. **Summary must be actionable**: Agent should understand what tool does
2. **Maximum 10 words**: Force brevity
3. **No parameter details**: Just the capability
4. **Alphabetical or logical ordering**: Easy to scan

**Good summaries**:
- ✅ "Read file contents"
- ✅ "Search files by pattern"
- ✅ "Execute shell command"

**Bad summaries**:
- ❌ "File reader" (too vague)
- ❌ "Reads a file from the filesystem and returns its contents as a string" (too long)
- ❌ "read_file" (just repeating the name)

---

## Level 2: Decide (Get Details)

**Purpose**: Understand if tool fits the need

**Goal**: Agent determines how to use the tool

**Content**:
- Tool name
- Detailed description (2-3 sentences)
- Parameter names (not full specs)
- Usage example

**Example call**:
```php
discover_file_tools(tool: "read_file")
```

**Example response**:
```json
{
  "name": "read_file",
  "description": "Reads a file from the filesystem. Supports reading specific line ranges for large files. Returns file contents as string.",
  "parameters": ["file_path", "offset", "limit"],
  "usage": "read_file(file_path='/path/to/file.txt')",
  "returns": "string"
}
```

**Token cost**: ~50 tokens

**Agent workflow**:
```
Agent sees: Takes file_path, optional offset/limit
Agent thinks: "I just need to read whole file. I'll use: read_file(file_path='config.yaml')"
Agent has enough info to use the tool
```

### Level 2 Design Rules

1. **Description is concise**: 2-3 sentences maximum
2. **Parameters listed by name**: Agent sees what's needed
3. **Usage example provided**: Shows common case
4. **Return type mentioned**: Agent knows what to expect

**Good Level 2**:
```json
{
  "name": "search_files",
  "description": "Searches for files matching a glob pattern. Supports recursive search and file type filtering. Returns list of matching file paths.",
  "parameters": ["pattern", "path", "recursive", "file_type"],
  "usage": "search_files(pattern='*.php', path='src/', recursive=true)",
  "returns": "array"
}
```

**Bad Level 2** (too much detail):
```json
{
  "name": "search_files",
  "description": "Searches for files matching a glob pattern. Supports recursive search and file type filtering. Can handle large directories efficiently using lazy evaluation. Supports advanced patterns including ** for recursive matching, ? for single character wildcards, and [abc] for character sets. Results are returned sorted by modification time by default. Thread-safe implementation allows concurrent searches...",
  "parameters": [...],  // Full parameter specs
  "examples": [...],    // Multiple examples
  ...
}
```

---

## Level 3: Deep Dive (Full Specification)

**Purpose**: Understand advanced usage and edge cases

**Goal**: Agent learns complete API for complex scenarios

**Content**:
- Everything from Level 2
- Full parameter specifications
- Multiple examples
- Error conditions
- Edge cases
- Performance notes

**Example call**:
```php
discover_file_tools(tool: "read_file", detail: "full")
```

**OR parameter-specific**:
```php
discover_file_tools(tool: "read_file", parameter: "offset")
```

**Example response (full)**:
```json
{
  "name": "read_file",
  "description": "Reads a file from the filesystem. Supports reading specific line ranges for large files. Returns file contents as string.",
  "parameters": {
    "file_path": {
      "type": "string",
      "description": "Absolute path to the file to read. Must exist and be readable.",
      "required": true,
      "validation": "Must be absolute path. Relative paths will throw PathException."
    },
    "offset": {
      "type": "integer",
      "description": "Line number to start reading from (1-indexed)",
      "required": false,
      "default": 1,
      "validation": "Must be positive integer. Values > file length return empty string."
    },
    "limit": {
      "type": "integer",
      "description": "Maximum number of lines to read",
      "required": false,
      "default": null,
      "validation": "Must be positive integer. Null means read to end of file."
    }
  },
  "returns": {
    "type": "string",
    "description": "File contents. Empty string if file is empty or offset > file length."
  },
  "examples": [
    {
      "code": "read_file(file_path='/var/log/app.log')",
      "description": "Read entire file"
    },
    {
      "code": "read_file(file_path='/var/log/app.log', offset=100, limit=50)",
      "description": "Read lines 100-150"
    }
  ],
  "errors": [
    {
      "type": "FileNotFoundException",
      "when": "File does not exist at specified path"
    },
    {
      "type": "PermissionException",
      "when": "File exists but is not readable"
    },
    {
      "type": "PathException",
      "when": "file_path is not absolute"
    }
  ],
  "notes": [
    "For files > 10MB, consider using offset/limit to read in chunks",
    "Line endings are preserved as-is (\\n or \\r\\n)",
    "Binary files return raw bytes as string"
  ]
}
```

**Example response (parameter-specific)**:
```json
{
  "tool": "read_file",
  "parameter": "offset",
  "type": "integer",
  "description": "Line number to start reading from (1-indexed)",
  "required": false,
  "default": 1,
  "validation": "Must be positive integer. Values > file length return empty string.",
  "examples": [
    "offset=1 (default - start from beginning)",
    "offset=100 (skip first 99 lines)",
    "offset=9999 (returns empty string if file < 9999 lines)"
  ]
}
```

**Token cost**: ~200 tokens for full, ~50 tokens for parameter

**Agent workflow**:
```
Agent: "I need to read a huge log file. What's offset do exactly?"
Agent calls: discover_file_tools(tool: "read_file", parameter: "offset")
Agent sees: "Line number to start from (1-indexed)"
Agent uses: read_file(file_path='/var/log/huge.log', offset=1000000, limit=100)
```

### Level 3 Design Rules

1. **Complete but concise**: Everything needed, nothing more
2. **Structured format**: Easy to parse
3. **Examples for complex cases**: Show non-obvious usage
4. **Error conditions documented**: Agent can anticipate failures
5. **Performance notes when relevant**: Help agent optimize

**Level 3 should be rare**: Most tools are simple enough that Level 2 is sufficient.

---

## Comparison: Token Usage

### 50 Tools, Agent Needs 3

**Without progressive disclosure** (dump everything):
```
50 tools × 200 tokens (full spec) = 10,000 tokens
```

**With progressive disclosure**:
```
Level 1 (browse all):
  50 tools × 10 tokens = 500 tokens

Level 2 (details for 3 relevant):
  3 tools × 50 tokens = 150 tokens

Level 3 (deep dive on 1):
  1 tool × 200 tokens = 200 tokens

Total: 850 tokens (91% reduction)
```

### 500 Tools, Agent Needs 5

**Without progressive disclosure**:
```
Impossible - would exceed context limits
500 × 200 = 100,000 tokens
```

**With progressive disclosure**:
```
Level 1: 500 × 10 = 5,000 tokens
Level 2: 5 × 50 = 250 tokens
Level 3: 1 × 200 = 200 tokens

Total: 5,450 tokens (95% reduction, scales!)
```

---

## Implementation Patterns

### Pattern 1: Levels via Parameters

```php
class DiscoverToolsTool implements Tool
{
    public function __invoke(array $args): array {
        // Level 1: No arguments
        if (empty($args)) {
            return $this->level1_browse();
        }

        // Level 2: Tool name only
        if (isset($args['tool']) && !isset($args['parameter'])) {
            return $this->level2_details($args['tool']);
        }

        // Level 3: Tool + parameter OR detail="full"
        if (isset($args['parameter'])) {
            return $this->level3_parameter($args['tool'], $args['parameter']);
        }

        if (isset($args['detail']) && $args['detail'] === 'full') {
            return $this->level3_full($args['tool']);
        }

        throw new InvalidArgumentException('Invalid arguments');
    }
}
```

### Pattern 2: Separate Tools per Level

```php
// Simpler but more tools
$agent
    ->withTool(new ListToolsTool($registry))           // Level 1
    ->withTool(new DescribeToolTool($registry))        // Level 2
    ->withTool(new GetToolSpecTool($registry))         // Level 3
```

**Recommendation**: Use Pattern 1 (single tool with parameters) for consistency.

---

## Agent Discovery: Same Pattern

Progressive disclosure applies to agents too:

### Level 1: List Agents
```php
discover_agents()
→ [
  {"name": "explorer", "summary": "Search codebase"},
  {"name": "planner", "summary": "Design implementation"},
  {"name": "reviewer", "summary": "Review code quality"}
]
```

### Level 2: Agent Details
```php
discover_agents(agent: "explorer")
→ {
  "name": "explorer",
  "description": "Searches codebase for files, patterns, and code",
  "whenToUse": "Use when you need to find code or understand structure",
  "capabilities": ["file-search", "code-search"],
  "constraints": ["read-only"]
}
```

### Level 3: Full Agent Spec
```php
discover_agents(agent: "explorer", detail: "full")
→ {
  "name": "explorer",
  "description": "...",
  "whenToUse": "...",
  "systemPrompt": "You are a codebase exploration specialist...",
  "tools": ["read_file", "search_files", "grep"],
  "prohibitedTools": ["write_file", "edit_file"],
  "examples": [...]
}
```

---

## Best Practices

### 1. Default to Level 1

When agent first encounters a registry, it should browse (Level 1):

```
Agent: "I need to work with files. What's available?"
Agent calls: discover_file_tools()  // No arguments = Level 1
```

### 2. Level 2 for Relevance Check

When agent identifies potentially relevant tools:

```
Agent: "read_file and write_file look relevant. Let me check both."
Agent calls: discover_file_tools(tool: "read_file")
Agent calls: discover_file_tools(tool: "write_file")
```

### 3. Level 3 Only When Necessary

Reserve Level 3 for complex or unclear cases:

```
Agent: "I need to read a 10GB log file. What are the performance implications?"
Agent calls: discover_file_tools(tool: "read_file", detail: "full")
Agent sees: "For files > 10MB, use offset/limit to read in chunks"
```

### 4. Cache Discovery Results

If agent will use same tool repeatedly, cache the discovery:

```
Agent discovers read_file once
Agent uses read_file multiple times
Agent doesn't re-discover each time
```

(Implementation detail for future optimization)

---

## Anti-Patterns

### ❌ Dumping Too Much at Level 2

```json
// BAD - Level 2 shouldn't have full parameter specs
{
  "name": "read_file",
  "description": "...",
  "parameters": {
    "file_path": {
      "type": "string",
      "description": "Absolute path to file...",
      "required": true,
      "validation": "...",
      "examples": [...]
    },
    // ... full specs for all parameters
  }
}
```

**Fix**: Level 2 should only list parameter names, not full specs.

### ❌ Level 1 Without Summaries

```json
// BAD - Just names, no summaries
{
  "tools": ["read_file", "write_file", "search_files", ...]
}
```

**Fix**: Always include one-line summary.

### ❌ No Level 3

```php
// BAD - Only two levels
if (empty($args)) {
    return $this->listTools();
}
return $this->describeToolDetails($args['tool']);
```

**Fix**: Always support three levels for completeness.

---

## Validation

Every discovery tool should support this test:

```php
test('discovery tool has three levels', function() {
    $tool = new DiscoverToolsTool('discover_test', $registry);

    // Level 1: List
    $level1 = $tool([]);
    expect($level1)->toHaveKey('tools');
    expect($level1['tools'][0])->toHaveKeys(['name', 'summary']);

    // Level 2: Details
    $level2 = $tool(['tool' => 'read_file']);
    expect($level2)->toHaveKeys(['name', 'description', 'parameters', 'usage']);

    // Level 3: Full spec
    $level3 = $tool(['tool' => 'read_file', 'detail' => 'full']);
    expect($level3)->toHaveKey('parameters');
    expect($level3['parameters']['file_path'])->toHaveKeys(['type', 'description', 'required']);
});
```

---

## Conclusion

Progressive disclosure is essential for:
- Context efficiency (95%+ reduction)
- Agent decision-making (relevant info only)
- Scalability (unlimited tools possible)

The three-level system balances:
- **Discoverability** (Level 1: What exists?)
- **Usability** (Level 2: How do I use it?)
- **Completeness** (Level 3: What are the edge cases?)

**Next**: See `03-implementation-guide.md` for step-by-step implementation instructions.
