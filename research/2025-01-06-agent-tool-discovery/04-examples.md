# Discovery Tools: Usage Examples

**Date**: 2025-01-06
**Purpose**: Real-world examples demonstrating discovery tools and progressive disclosure

## Example 1: Simple File Operations

**Scenario**: Agent needs to read a configuration file

### Agent Workflow

**User**: "Read the database configuration file"

**Agent Step 1**: Discover available file tools

```php
// Agent calls Level 1
discover_file_tools()
```

**Response**:
```json
{
  "tools": [
    {"name": "read_file", "summary": "Read file contents"},
    {"name": "write_file", "summary": "Write to file"},
    {"name": "search_files", "summary": "Find files by pattern"}
  ]
}
```

**Agent thinking**: "I need 'read_file'. Let me get usage details."

**Agent Step 2**: Get tool details

```php
// Agent calls Level 2
discover_file_tools(tool: "read_file")
```

**Response**:
```json
{
  "name": "read_file",
  "description": "Reads a file from the filesystem. Supports line ranges.",
  "parameters": ["file_path", "offset", "limit"],
  "usage": "read_file(file_path='/path/to/file.txt')",
  "returns": "string"
}
```

**Agent thinking**: "Simple - just need file_path."

**Agent Step 3**: Use the tool

```php
// Agent calls the actual tool
read_file(file_path: "config/database.yaml")
```

**Token usage**:
- Level 1: 90 tokens (3 tools listed)
- Level 2: 50 tokens (read_file details)
- Total: 140 tokens vs 600 tokens if all file tools were pre-injected

---

## Example 2: Complex Search with Parameters

**Scenario**: Agent needs to find test files in a large codebase

### Agent Workflow

**User**: "Find all test files but skip vendor directory"

**Agent Step 1**: Browse file tools

```php
discover_file_tools()
```

**Response**: [3 file tools listed]

**Agent Step 2**: Get search_files details

```php
discover_file_tools(tool: "search_files")
```

**Response**:
```json
{
  "name": "search_files",
  "description": "Searches for files matching a glob pattern. Supports recursive search and filtering.",
  "parameters": ["pattern", "path", "recursive", "file_type"],
  "usage": "search_files(pattern='*.php', path='src/')",
  "returns": "array"
}
```

**Agent thinking**: "I can use pattern and path. But how do I exclude vendor? Let me check parameters."

**Agent Step 3**: Get parameter details

```php
// Agent calls Level 3 for specific parameter
discover_file_tools(tool: "search_files", parameter: "path")
```

**Response**:
```json
{
  "tool": "search_files",
  "parameter": "path",
  "type": "string",
  "description": "Directory to search in",
  "required": false,
  "default": ".",
  "examples": [
    "path='src/'",
    "path='.' (default - current directory)"
  ]
}
```

**Agent thinking**: "Path doesn't help with exclusion. Let me check the full spec."

**Agent Step 4**: Get full specification

```php
discover_file_tools(tool: "search_files", detail: "full")
```

**Response**:
```json
{
  "name": "search_files",
  ...
  "notes": [
    "Symlinks followed by default",
    "Hidden files (starting with .) excluded by default",
    "To exclude directories, use negative patterns: !vendor/**"
  ]
}
```

**Agent thinking**: "Ah! Use negative pattern."

**Agent Step 5**: Use the tool correctly

```php
search_files(
    pattern: "*.test.php",
    path: "."
)
// Then filter results to exclude vendor in post-processing
// OR
search_files(pattern: "{tests,src}/**/*.test.php", path: ".")
```

**Token usage**:
- Level 1: 90 tokens
- Level 2: 50 tokens (search_files)
- Level 3: 50 tokens (path parameter)
- Level 3: 200 tokens (full spec)
- Total: 390 tokens

**Value**: Agent learned to use tool correctly without needing all details upfront.

---

## Example 3: Multi-Domain Task

**Scenario**: Agent needs to read files, run shell commands, and use CRM API

### Agent Workflow

**User**: "Read config/api.yaml, run the database migration script, and update the CRM with new API endpoint"

**Agent Step 1**: Identify needed domains

**Agent thinking**: "I need:
1. File operations (read config)
2. Batch operations (run script)
3. CRM API (update endpoint)"

**Agent Step 2**: Discover file tools

```php
discover_file_tools()
```

**Response**: [File tools listed]

**Agent Step 3**: Discover batch tools

```php
discover_batch_tools()
```

**Response**:
```json
{
  "tools": [
    {"name": "run_command", "summary": "Execute shell command"},
    {"name": "run_script", "summary": "Run script file"},
    {"name": "run_batch", "summary": "Execute batch of commands"}
  ]
}
```

**Agent Step 4**: Discover CRM tools

```php
discover_crm_tools()
```

**Response**:
```json
{
  "tools": [
    {"name": "get_contact", "summary": "Retrieve contact info"},
    {"name": "update_contact", "summary": "Update contact details"},
    {"name": "create_contact", "summary": "Create new contact"},
    {"name": "update_endpoint", "summary": "Update API endpoint configuration"}
  ]
}
```

**Agent Step 5**: Get details for relevant tools

```php
// File tool
discover_file_tools(tool: "read_file")

// Batch tool
discover_batch_tools(tool: "run_script")

// CRM tool
discover_crm_tools(tool: "update_endpoint")
```

**Agent Step 6**: Execute the task

```php
// 1. Read config
$config = read_file(file_path: "config/api.yaml");

// 2. Run migration
run_script(script_path: "scripts/migrate-database.sh");

// 3. Update CRM
update_endpoint(
    endpoint_name: "api_v2",
    url: parse_from($config)
);
```

**Token usage**:
- 3 × Level 1 (browse domains): 270 tokens
- 3 × Level 2 (get details): 150 tokens
- Total: 420 tokens

**Without discovery**: Would need ALL tools from ALL domains pre-injected = ~3,000 tokens

**Savings**: 86% context reduction

---

## Example 4: Agent Discovery and Delegation

**Scenario**: Complex task requiring specialized agent

### Agent Workflow

**User**: "Find all security vulnerabilities in the authentication code"

**Agent Step 1**: Recognize need for specialization

**Agent thinking**: "This is complex. Are there specialized agents?"

```php
discover_agents()
```

**Response**:
```json
{
  "agents": [
    {"name": "explorer", "summary": "Search codebase"},
    {"name": "planner", "summary": "Design implementation"},
    {"name": "reviewer", "summary": "Review code quality"},
    {"name": "security-analyzer", "summary": "Analyze security issues"}
  ]
}
```

**Agent Step 2**: Get security-analyzer details

```php
discover_agents(agent: "security-analyzer")
```

**Response**:
```json
{
  "name": "security-analyzer",
  "description": "Specialized agent for identifying security vulnerabilities in code",
  "whenToUse": "Use when you need to find security issues, analyze attack vectors, or review code for vulnerabilities",
  "capabilities": ["security-analysis", "vulnerability-detection", "threat-modeling"],
  "constraints": ["read-only", "no-code-execution"]
}
```

**Agent thinking**: "Perfect match. Let me delegate."

**Agent Step 3**: Spawn specialized agent

```php
spawn_subagent(
    name: "security-analyzer",
    task: "Find security vulnerabilities in authentication code"
)
```

**Token usage**:
- Level 1: 100 tokens (4 agents)
- Level 2: 80 tokens (security-analyzer details)
- Total: 180 tokens

**Alternative (all agents pre-injected)**: 1,000+ tokens

---

## Example 5: Progressive Learning

**Scenario**: Agent starts unfamiliar, learns incrementally

### First Request

**User**: "Process the customer data file"

**Agent**: "I don't know about customer data. Let me explore."

```php
// Discover file tools
discover_file_tools()

// Agent sees: read_file, write_file, search_files

// Try read_file
discover_file_tools(tool: "read_file")

// Read the file
read_file(file_path: "data/customers.csv")
```

**Agent learns**: "There's a read_file tool I can use for files."

### Second Request (Same Session)

**User**: "Now update the customer records"

**Agent**: "I know about file tools. Let me check write_file."

```php
// Skip Level 1 (already knows file tools exist)

// Go straight to Level 2
discover_file_tools(tool: "write_file")

// Use it
write_file(file_path: "data/customers.csv", content: updated_data)
```

**Agent learns**: "I can update files with write_file."

### Third Request (Same Session)

**User**: "Find all CSV files in data directory"

**Agent**: "I vaguely remember there's a search tool."

```php
// Quick Level 1 refresh
discover_file_tools()

// Oh yes, search_files

// Get details
discover_file_tools(tool: "search_files")

// Use it
search_files(pattern: "*.csv", path: "data/")
```

**Pattern**: Agent builds knowledge incrementally, doesn't need everything upfront.

---

## Example 6: Error Handling

**Scenario**: Agent uses tool incorrectly, learns from error

### Attempt 1: Naive Usage

**User**: "Read the first 100 lines of a large log file"

**Agent**: "Let me read the file."

```php
read_file(file_path: "/var/log/application.log")
```

**Response**: [10MB file returned, context limit exceeded]

**Error**: "Response too large for context"

### Attempt 2: Learn from Error

**Agent**: "The file is too large. Let me check if there are options."

```php
discover_file_tools(tool: "read_file", detail: "full")
```

**Response**:
```json
{
  ...
  "parameters": {
    "offset": {...},
    "limit": {...}
  },
  "notes": [
    "For files > 10MB, use offset/limit to read in chunks"
  ]
}
```

**Agent**: "Ah! I can use limit."

```php
read_file(
    file_path: "/var/log/application.log",
    offset: 1,
    limit: 100
)
```

**Success**: Gets first 100 lines only.

**Pattern**: Level 3 (full spec) helps agent recover from errors.

---

## Example 7: Context-Aware Discovery

**Scenario**: Agent optimizes based on context size

### Small Context Available

**Agent**: "I have plenty of context. Let me get full details upfront."

```php
// Multiple Level 2 calls in parallel
discover_file_tools(tool: "read_file")
discover_file_tools(tool: "write_file")
discover_file_tools(tool: "search_files")

// Agent caches all details for reuse
```

### Large Context Used

**Agent**: "Context is filling up. Let me be selective."

```php
// Level 1 only - get list
discover_file_tools()

// Level 2 only for immediate need
discover_file_tools(tool: "read_file")

// Skip Level 2 for other tools until needed
```

**Pattern**: Agent can optimize discovery based on context constraints.

---

## Example 8: Batch Discovery

**Scenario**: Agent needs multiple tools from same domain

### Inefficient Approach

```php
// Agent makes 5 separate calls
discover_file_tools(tool: "read_file")
discover_file_tools(tool: "write_file")
discover_file_tools(tool: "search_files")
discover_file_tools(tool: "edit_file")
discover_file_tools(tool: "delete_file")
```

### Optimized Approach

```php
// Agent calls Level 1 once
$all_tools = discover_file_tools()

// Agent gets Level 2 for all relevant tools in parallel
// (Hypothetical batch endpoint)
discover_file_tools_batch(tools: [
    "read_file",
    "write_file",
    "search_files"
])
```

**Future enhancement**: Batch discovery for multiple tools at once.

---

## Example 9: Category Filtering

**Scenario**: Agent needs only specific type of agents

### Without Filtering

```php
discover_agents()
// Returns all 20 agents
```

### With Filtering

```php
discover_agents(category: "code-analysis")
// Returns only: [explorer, reviewer, security-analyzer]
```

**Benefit**: Reduces noise, shows only relevant options.

---

## Example 10: Complete Real-World Task

**Scenario**: "Analyze code quality and create report"

### Full Agent Workflow

```php
User: "Analyze the src/ directory for code quality issues and create a report"

// Step 1: Discover agents
Agent calls: discover_agents()
Response: [explorer, planner, reviewer, ...]

Agent thinking: "reviewer sounds relevant"

// Step 2: Get reviewer details
Agent calls: discover_agents(agent: "reviewer")
Response: {
  "name": "reviewer",
  "whenToUse": "Use when analyzing code quality, finding bugs, or reviewing code",
  ...
}

Agent thinking: "Perfect. Let me delegate to reviewer."

// Step 3: Spawn reviewer agent
Agent calls: spawn_subagent(name: "reviewer", task: "Analyze src/ for quality issues")

// Reviewer agent starts its own workflow

// Step 4: Reviewer discovers file tools
Reviewer calls: discover_file_tools()
Response: [read_file, search_files, ...]

// Step 5: Reviewer gets search details
Reviewer calls: discover_file_tools(tool: "search_files")
Response: {usage: "search_files(pattern='*.php', path='src/')"}

// Step 6: Reviewer finds files
Reviewer calls: search_files(pattern: "*.php", path: "src/")
Response: [file1.php, file2.php, ...]

// Step 7: Reviewer analyzes each file
Reviewer calls: read_file(file_path: "src/file1.php")
Reviewer analyzes code...

// Step 8: Reviewer generates report
Reviewer returns: {issues: [...], recommendations: [...]}

// Step 9: Main agent writes report
Agent calls: discover_file_tools(tool: "write_file")
Agent calls: write_file(
    file_path: "reports/code-quality.md",
    content: formatted_report
)

// Done
```

**Token efficiency**:
- Without discovery: ~15,000 tokens (all tools + all agents pre-injected)
- With discovery: ~800 tokens (selective discovery)
- **Savings**: 95%

---

## Conclusion

These examples demonstrate:
- ✅ Progressive disclosure reduces context by 85-95%
- ✅ Agent learns incrementally, building knowledge
- ✅ Multi-domain tasks are efficient with separate registries
- ✅ Specialized agents discovered on-demand
- ✅ Error recovery through Level 3 deep dive
- ✅ Context-aware optimization possible

The discovery tool pattern is **essential** for:
- Large tool sets (50+ tools)
- Multi-domain applications
- Long-running agent sessions
- Context-constrained environments
- Dynamic tool availability

**Result**: Agents can access unlimited capabilities while maintaining minimal context overhead.
